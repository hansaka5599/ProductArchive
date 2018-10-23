<?php
namespace Ecommistry\ProductArchive\Plugin\Controller\Product;

use Magento\Catalog\Controller\Product\View as ProductView;
use Magento\Framework\View\Result\PageFactory;
use Magento\Catalog\Model\Product as ModelProduct;
use Magento\Catalog\Helper\Product as HelperProduct;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Json\Helper\Data as JsonHelperData;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Catalog\Helper\Product\View as ViewHelper;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogInventory\Model\Spi\StockRegistryProviderInterface;
use Magento\CatalogInventory\Api\StockConfigurationInterface;

/**
 * Class View
 * @package Ecommistry\ProductArchive\Plugin\Controller\Product
 */
class View
{
    /**
     * @var ResultFactory
     */
    protected $resultFactory;

    /**
     * @var ForwardFactory
     */
    private $resultForwardFactory;

    /**
     * @var RedirectFactory
     */
    private $resultRedirectFactory;

    /**
     * @var RedirectInterface
     */
    private $redirect;

    /**
     * @var PageFactory
     */
    private $resultPageFactory;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var HelperProduct
     */
    private $helperProduct;

    /**
     * @var ManagerInterface
     */
    private $messageManager;

    /**
     * @var JsonHelperData
     */
    private $jsonHelperData;

    /**
     * @var ViewHelper
     */
    private $viewHelper;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var CategoryFactory
     */
    private $categoryFactory;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var StockConfigurationInterface
     */
    private $stockConfiguration;

    /**
     * @var StockRegistryProviderInterface
     */
    private $stockRegistryProvider;

    /**
     * View constructor.
     *
     * @param ResultFactory $resultFactory
     * @param ForwardFactory $resultForwardFactory
     * @param RedirectFactory $redirectFactory
     * @param RedirectInterface $redirect
     * @param PageFactory $resultPageFactory
     * @param StoreManagerInterface $storeManager
     * @param HelperProduct $helperProduct
     * @param ManagerInterface $messageManager
     * @param JsonHelperData $jsonHelperData
     * @param ViewHelper $viewHelper
     * @param LoggerInterface $logger
     * @param CategoryFactory $categoryFactory
     * @param ProductRepositoryInterface $productRepository
     */
    public function __construct(
        ResultFactory $resultFactory,
        ForwardFactory $resultForwardFactory,
        RedirectFactory $redirectFactory,
        RedirectInterface $redirect,
        PageFactory $resultPageFactory,
        StoreManagerInterface $storeManager,
        HelperProduct $helperProduct,
        ManagerInterface $messageManager,
        JsonHelperData $jsonHelperData,
        ViewHelper $viewHelper,
        LoggerInterface $logger,
        CategoryFactory $categoryFactory,
        ProductRepositoryInterface $productRepository,
        StockConfigurationInterface $stockConfiguration,
        StockRegistryProviderInterface $stockRegistryProvider
    ) {
        $this->resultFactory = $resultFactory;
        $this->resultForwardFactory = $resultForwardFactory;
        $this->resultRedirectFactory = $redirectFactory;
        $this->redirect = $redirect;
        $this->resultPageFactory = $resultPageFactory;
        $this->storeManager = $storeManager;
        $this->helperProduct = $helperProduct;
        $this->messageManager = $messageManager;
        $this->jsonHelperData = $jsonHelperData;
        $this->viewHelper = $viewHelper;
        $this->logger = $logger;
        $this->categoryFactory = $categoryFactory;
        $this->productRepository = $productRepository;
        $this->stockConfiguration = $stockConfiguration;
        $this->stockRegistryProvider = $stockRegistryProvider;
    }

    /**
     * @param ProductView $subject
     * @param \Closure $proceed
     * @return \Magento\Framework\Controller\Result\Forward|\Magento\Framework\Controller\Result\Redirect
     */
    public function aroundExecute(
        ProductView $subject,
        \Closure $proceed
    ) {
        // Get initial data from request
        $categoryId = (int) $subject->getRequest()->getParam('category', false);
        $productId = (int) $subject->getRequest()->getParam('id');
        $specifyOptions = $subject->getRequest()->getParam('options');

        if ($subject->getRequest()->isPost() && $subject->getRequest()->getParam(ProductView::PARAM_NAME_URL_ENCODED)) {
            $product = $this->_initProduct($subject);
            if (!$product) {
                return $this->noProductRedirect($subject);
            }
            if ($specifyOptions) {
                $notice = $product->getTypeInstance()->getSpecifyOptionMessage();
                $this->messageManager->addNotice($notice);
            }
            if ($subject->getRequest()->isAjax()) {
                return $subject->getResponse()->representJson(
                    $this->jsonHelperData->jsonEncode([
                        'backUrl' => $this->redirect->getRedirectUrl()
                    ])
                );
            }

            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setRefererOrBaseUrl();
            return $resultRedirect;
        }

        // Prepare helper and params
        $params = new \Magento\Framework\DataObject();
        $params->setCategoryId($categoryId);
        $params->setSpecifyOptions($specifyOptions);
        $currentProduct = $this->productRepository->getById($productId, true, $this->storeManager->getStore()->getId());

        // Render page
        try {
            $productStock = $this->getStockItem($productId);
            if(
                (!$productStock->getIsInStock())
                &&
                ($currentProduct->getData('animates_visible_if_oos') == 0)
            ) {
                throw new \Magento\Framework\Exception\NoSuchEntityException(
                    __('Product not allow for display')
                );
            }
            $page = $this->resultPageFactory->create();
            $this->viewHelper->prepareAndRender($page, $productId, $subject, $params);
            return $page;
        } catch (NoSuchEntityException $e) {
            if ($currentProduct) {
                $isArchive = $currentProduct->getArchive();
                if ($isArchive) {
                    $statusCode = 301;
                    $archiveUrl = $currentProduct->getUrlToRedirect();
                    if (!$archiveUrl) {
                        $categories = $currentProduct->getCategoryIds();
                        rsort($categories);
                        if ($categories) {
                            foreach ($categories as $category) {
                                /** @var \Magento\Catalog\Model\Category $currentCategory */
                                $currentCategory = $this->categoryFactory->create();
                                $currentCategory = $currentCategory->load($category);
                                if($currentCategory && $currentCategory->getIsActive()){
                                    $archiveUrl = $currentCategory->getUrl();
                                    break;
                                }
                            }
                        } else {
                            $statusCode = 410;
                            $archiveUrl = $this->storeManager->getStore()->getBaseUrl();
                        }
                    }

                    $resultRedirect = $this->resultFactory->create('redirect', array('statusCode' => $statusCode));
                    $resultRedirect->setUrl($archiveUrl);
                    return $resultRedirect;
                }
            }

            return $this->noProductRedirect($subject);
        } catch (\Exception $e) {
            $this->logger->critical($e);
            $resultForward = $this->resultForwardFactory->create();
            $resultForward->forward('noroute');
            return $resultForward;
        }
    }

    /**
     * Initialize requested product object
     * @param ProductView $subject
     *
     * @return ModelProduct
     */
    protected function _initProduct($subject)
    {
        $categoryId = (int)$subject->getRequest()->getParam('category', false);
        $productId = (int)$subject->getRequest()->getParam('id');

        $params = new \Magento\Framework\DataObject();
        $params->setCategoryId($categoryId);

        return $this->helperProduct->initProduct($productId, $subject, $params);
    }

    /**
     * Redirect if product failed to load
     * @param ProductView $subject
     *
     * @return \Magento\Framework\Controller\Result\Redirect|\Magento\Framework\Controller\Result\Forward
     */
    protected function noProductRedirect($subject)
    {
        $store = $subject->getRequest()->getQuery('store');
        if (isset($store) && !$subject->getResponse()->isRedirect()) {
            $resultRedirect = $this->resultRedirectFactory->create();
            return $resultRedirect->setPath('');
        } elseif (!$subject->getResponse()->isRedirect()) {
            $resultForward = $this->resultForwardFactory->create();
            $resultForward->forward('noroute');
            return $resultForward;
        }
    }

    /**
     * @param $productId
     * @return \Magento\CatalogInventory\Api\Data\StockItemInterface
     */
    public function getStockItem($productId)
    {
        $scopeId = $this->stockConfiguration->getDefaultScopeId();
        return $this->stockRegistryProvider->getStockItem($productId, $scopeId);
    }
}