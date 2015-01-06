<?php
/**
 * @copyright Copyright (c) 2014 X.commerce, Inc. (http://www.magentocommerce.com)
 */

$installer = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->create(
    'Magento\Catalog\Model\Resource\Setup',
    ['resourceName' => 'catalog_setup']
);
require __DIR__ . '/categories_rollback.php';

/** @var $productCollection \Magento\Catalog\Model\Resource\Product\Collection */
$productCollection = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()
    ->create('\Magento\Catalog\Model\Resource\Product\Collection');

$productCollection->load()->delete();
