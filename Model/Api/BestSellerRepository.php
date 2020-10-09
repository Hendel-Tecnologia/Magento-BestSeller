<?php

namespace Hendel\BestSeller\Model\Api;

use \Hendel\BestSeller\Api\BestSellerRepositoryInterface;

class BestSellerRepository implements BestSellerRepositoryInterface
{
    /**
     * @var \Magento\Reports\Model\ResourceModel\Product\CollectionFactory
     */
    protected $productsFactory;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var \Magento\Catalog\Api\Data\ProductSearchResultsInterfaceFactory $searchResultsFactory
     */
    protected $searchResultsFactory;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\TimezoneInterface
     */
    protected $localeDate;


    /**
     * @param \Magento\Reports\Model\ResourceModel\Product\CollectionFactory $productsFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
     * @param \Magento\Catalog\Api\Data\ProductSearchResultsInterfaceFactory $searchResultsFactory
     */
    public function __construct(
        \Magento\Reports\Model\ResourceModel\Product\CollectionFactory $productsFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Catalog\Api\Data\ProductSearchResultsInterfaceFactory $searchResultsFactory,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate
    ) {
        $this->productsFactory = $productsFactory;
        $this->storeManager = $storeManager;
        $this->productRepository = $productRepository;
        $this->searchResultsFactory  = $searchResultsFactory;
        $this->localeDate = $localeDate;
    }

    public function getProductCollection()
    {
        $storeId = $this->storeManager->getStore()->getId();
        $collection = $this->productsFactory->create()
            ->addAttributeToSelect('*')
            ->setVisibility(
                [
                    \Magento\Catalog\Model\Product\Visibility::VISIBILITY_IN_CATALOG,
                    \Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH
                ]
            )
            ->addMinimalPrice()
            ->addFinalPrice()
            ->addTaxPercents()
            //            ->addAttributeToSelect($this->catalogConfig->getProductAttributes())
            ->setStoreId($storeId)
            //            ->addStoreFilter();
            ->addStoreFilter($storeId);
        return $collection;
    }

    /**
     * {@inheritdoc}
     */
    public function getList($limit = 10)
    {
        $collection = $this->getProductCollection();
        $connection = $collection->getConnection();
        $orderTableAliasName = $connection->quoteIdentifier('order');
        $orderJoinCondition = [
            $orderTableAliasName . '.entity_id = order_items.order_id',
            $connection->quoteInto("{$orderTableAliasName}.state <> ?", \Magento\Sales\Model\Order::STATE_CANCELED),
        ];

        $collection->setPageSize(
            $limit
        )->setCurPage(
            1
        )->getSelect()
            ->from(
                ['order_items' => $collection->getTable('sales_order_item')],
                ['ordered_qty' => 'SUM(order_items.qty_ordered)', 'product_id']
            )->joinInner(
                ['order' => $collection->getTable('sales_order')],
                implode(' AND ', $orderJoinCondition),
                []
            )->where(
                'e.entity_id = order_items.product_id and parent_item_id IS NULL'
            )->group(
                'order_items.product_id'
            )->order(
                'ordered_qty ' . \Magento\Framework\DB\Select::SQL_DESC
            )->having(
                'SUM(order_items.qty_ordered) > ?',
                0
            );

        $collection->load();
        $collection->addCategoryIds();

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->count());

        return $searchResults;
    }
}
