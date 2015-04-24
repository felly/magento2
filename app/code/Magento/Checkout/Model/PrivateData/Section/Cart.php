<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\Checkout\Model\PrivateData\Section;

use Magento\Customer\Model\PrivateData\Section\SectionSourceInterface;

/**
 * Cart source
 */
class Cart extends \Magento\Framework\Object implements SectionSourceInterface
{
    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var \Magento\Checkout\Model\Cart
     */
    protected $checkoutCart;

    /**
     * @var \Magento\Catalog\Model\Resource\Url
     */
    protected $catalogUrl;

    /**
     * @var \Magento\Quote\Model\Quote|null
     */
    protected $quote = null;

    /**
     * @var \Magento\Checkout\Helper\Data
     */
    protected $checkoutHelper;

    /**
     * @var \Magento\Catalog\Model\Product\Image\View
     */
    protected $productImageView;

    /**
     * @var \Magento\Msrp\Helper\Data
     */
    protected $msrpHelper;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlBuilder;

    /**
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Catalog\Model\Resource\Url $catalogUrl
     * @param \Magento\Checkout\Model\Cart $checkoutCart
     * @param \Magento\Checkout\Helper\Data $checkoutHelper
     * @param array $data
     */
    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Catalog\Model\Resource\Url $catalogUrl,
        \Magento\Checkout\Model\Cart $checkoutCart,
        \Magento\Checkout\Helper\Data $checkoutHelper,
        \Magento\Catalog\Model\Product\Image\View $productImageView,
        \Magento\Msrp\Helper\Data $msrpHelper,
        \Magento\Framework\UrlInterface $urlBuilder,
        array $data = []
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->catalogUrl = $catalogUrl;
        $this->checkoutCart = $checkoutCart;
        $this->checkoutHelper = $checkoutHelper;
        $this->productImageView = $productImageView;
        $this->msrpHelper = $msrpHelper;
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function getSectionData()
    {
        $totals = $this->getQuote()->getTotals();
        return [
            'summary_count' => $this->getSummaryCount(),
            'subtotal' => isset($totals['subtotal'])
                ? $this->checkoutHelper->formatPrice($totals['subtotal']->getValue())
                : 0,
            'cart_empty_message' => '',
            'possible_onepage_checkout' => $this->isPossibleOnepageCheckout(),
            'items' => $this->getRecentItems(),
        ];
    }

    /**
     * Get active quote
     *
     * @return \Magento\Quote\Model\Quote
     */
    protected function getQuote()
    {
        if (null === $this->quote) {
            $this->quote = $this->checkoutSession->getQuote();
        }
        return $this->quote;
    }

    /**
     * Get shopping cart items qty based on configuration (summary qty or items qty)
     *
     * @return int|float
     */
    protected function getSummaryCount()
    {
        if ($this->getData('summary_qty')) {
            return $this->getData('summary_qty');
        }
        return $this->checkoutCart->getSummaryQty();
    }

    /**
     * Check if one page checkout is available
     *
     * @return bool
     */
    protected function isPossibleOnepageCheckout()
    {
        return $this->checkoutHelper->canOnepageCheckout() && !$this->getQuote()->getHasError();
    }

    /**
     * Get array of last added items
     *
     * @return \Magento\Quote\Model\Quote\Item[]
     */
    protected function getRecentItems()
    {
        $items = [];
        if (!$this->getSummaryCount()) {
            return $items;
        }

        $allItems = array_reverse($this->getItems());
        foreach ($allItems as $item) {
            /* @var $item \Magento\Quote\Model\Quote\Item */
            if (!$item->getProduct()->isVisibleInSiteVisibility()) {
                $productId = $item->getProduct()->getId();
                $products = $this->catalogUrl->getRewriteByProductStore([$productId => $item->getStoreId()]);
                if (!isset($products[$productId])) {
                    continue;
                }
                $urlDataObject = new \Magento\Framework\Object($products[$productId]);
                $item->getProduct()->setUrlDataObject($urlDataObject);
            }
            $this->productImageView->init($item->getProduct(), 'mini_cart_product_thumbnail', 'Magento_Catalog');
            //TODO: Retrieve item info prom item pool

            $items[] = [
                'product_type' => $item->getProductType(),
                'qty' => $this->getQty($item),
                'item_id' => $item->getId(),
                'configure_url' => $this->getConfigureUrl($item),
                'is_visible_in_site_visibility' => $item->getProduct()->isVisibleInSiteVisibility(),
                'name' => $this->getProductName($item),
                'url' => $this->getProductUrl($item),
                'has_url' => $this->hasProductUrl($item),
                'price' => $this->checkoutHelper->formatPrice($item->getCalculationPrice()),
                'image' => [
                    'src' => $this->productImageView->getUrl(),
                    'alt' => $this->productImageView->getLabel(),
                    'width' => $this->productImageView->getWidth(),
                    'height' => $this->productImageView->getHeight(),
                ],
                'canApplyMsrp' => $this->msrpHelper->isShowBeforeOrderConfirm($item->getProduct())
                    && $this->msrpHelper->isMinimalPriceLessMsrp($item->getProduct())
            ];
        }
        return $items;
    }

    /**
     * Return customer quote items
     *
     * @return \Magento\Quote\Model\Quote\Item[]
     */
    protected function getItems()
    {
        if ($this->getCustomQuote()) {
            return $this->getCustomQuote()->getAllVisibleItems();
        }
        return $this->getQuote()->getAllVisibleItems();
    }

    /**
     * Get item configure url
     * @param \Magento\Quote\Model\Quote\Item  $item
     *
     * @return string
     */
    public function getConfigureUrl($item)
    {
        return $this->urlBuilder->getUrl(
            'checkout/cart/configure',
            ['id' => $item->getId(), 'product_id' => $item->getProduct()->getId()]
        );
    }

    /**
     * Get quote item qty
     * @param \Magento\Quote\Model\Quote\Item  $item
     *
     * @return float|int
     */
    public function getQty($item)
    {
        return $item->getQty() * 1;
    }

    /**
     * Check Product has URL
     * @param \Magento\Quote\Model\Quote\Item  $item
     *
     * @return bool
     */
    public function hasProductUrl($item)
    {
        if ($item->getRedirectUrl()) {
            return true;
        }

        $product = $item->getProduct();
        $option = $item->getOptionByCode('product_type');
        if ($option) {
            $product = $option->getProduct();
        }

        if ($product->isVisibleInSiteVisibility()) {
            return true;
        } else {
            if ($product->hasUrlDataObject()) {
                $data = $product->getUrlDataObject();
                if (in_array($data->getVisibility(), $product->getVisibleInSiteVisibilities())) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Retrieve URL to item Product
     * @param \Magento\Quote\Model\Quote\Item  $item
     *
     * @return string
     */
    public function getProductUrl($item)
    {
        if ($item->getRedirectUrl()) {
            return $item->getRedirectUrl();
        }

        $product = $item->getProduct();
        $option = $item->getOptionByCode('product_type');
        if ($option) {
            $product = $option->getProduct();
        }

        return $product->getUrlModel()->getUrl($product);
    }

    /**
     * Get item product name
     * @param \Magento\Quote\Model\Quote\Item  $item
     *
     * @return string
     */
    public function getProductName($item)
    {
        return $item->getProduct()->getName();
    }
}
