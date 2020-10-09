<?php

namespace Hendel\BestSeller\Api;

interface BestSellerRepositoryInterface
{
    /**
     * GET product identified by its URL key
     *
     * @api
     * @param int $limit
     * @return \Magento\Catalog\Api\Data\ProductSearchResultsInterface
     */
    public function getList($limit = 10);
}
