<?php

namespace Ecommistry\ProductArchive\Setup;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

/**
 * @codeCoverageIgnore
 */
class InstallData implements InstallDataInterface
{
    /**
     * EAV setup factory
     *
     * @var EavSetupFactory
     */
    private $eavSetupFactory;

    /**
     * Init
     *
     * @param EavSetupFactory $eavSetupFactory
     */
    public function __construct(EavSetupFactory $eavSetupFactory)
    {
        $this->eavSetupFactory = $eavSetupFactory;
    }

    /**
     * {@inheritdoc}
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);

        $eavSetup->addAttribute(
            Product::ENTITY,
            'archive',
            [
                'type' => 'int',
                'backend' => '',
                'frontend' => '',
                'label' => 'Archive',
                'input' => 'select',
                'class' => '0',
                'source' => 'Magento\Eav\Model\Entity\Attribute\Source\Boolean',
                'global' => ScopedAttributeInterface::SCOPE_STORE,
                'user_defined' => true,
                'default' => '0',
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => true,
                'unique' => false,
                'group' => 'Product Details',
                'position' => 260,
                'sort_order' => 165,
                'required' => false,
            ]
        );

        $eavSetup->addAttribute(
            Product::ENTITY,
            'url_to_redirect',
            [
                'type' => 'varchar',
                'label' => 'Redirect URL',
                'default' => '',
                'input' => 'text',
                'visible' => true,
                'required' => false,
                'searchable' => false,
                'filterable' => false,
                'filterable_in_search' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'user_defined' => true,
                'used_in_product_listing' => true,
                'unique' => false,
                'position' => 271,
                'sort_order' => 175,
                'global' => ScopedAttributeInterface::SCOPE_STORE,
                'group' => 'Product Details',
                'note'  => "Use Relative Or Absolute Links. Example: 'http://domain.com/redirect_url' or just 'redirect url' can be used. If there is no value for this field and Archive = Yes it will redirect to product category."
            ]
        );
    }
}
