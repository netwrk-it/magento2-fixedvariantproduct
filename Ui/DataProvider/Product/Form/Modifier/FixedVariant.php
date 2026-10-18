<?php
declare(strict_types=1);

namespace Netwrk\FixedVariantProduct\Ui\DataProvider\Product\Form\Modifier;

use Magento\Catalog\Model\Locator\LocatorInterface;
use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\AbstractModifier;
use Magento\Ui\Component\Container;
use Magento\Ui\Component\DynamicRows;
use Magento\Ui\Component\Form\Element\Checkbox;
use Magento\Ui\Component\Form\Element\DataType\Number;
use Magento\Ui\Component\Form\Element\DataType\Text;
use Magento\Ui\Component\Form\Element\Hidden;
use Magento\Ui\Component\Form\Element\Input;
use Magento\Ui\Component\Form\Element\Select;
use Magento\Ui\Component\Form\Field;
use Magento\Ui\Component\Form\Fieldset;
use Netwrk\FixedVariantProduct\Model\Config;
use Netwrk\FixedVariantProduct\Model\Product\Type\FixedVariant as Type;
use Netwrk\FixedVariantProduct\Model\ResourceModel\Package;
use Netwrk\FixedVariantProduct\Model\Source\SelectableProducts;

/**
 * "Samenstelling" section on the product edit page of a starter package
 */
class FixedVariant extends AbstractModifier
{
    public const FIELDSET = 'fixed_variant';
    public const DATA_SCOPE = 'fixed_variant';

    /**
     * @param LocatorInterface $locator
     * @param Package $package
     * @param SelectableProducts $selectableProducts
     * @param Config $config
     */
    public function __construct(
        private readonly LocatorInterface $locator,
        private readonly Package $package,
        private readonly SelectableProducts $selectableProducts,
        private readonly Config $config
    ) {
    }

    /**
     * @inheritdoc
     */
    public function modifyData(array $data)
    {
        $product = $this->locator->getProduct();
        if ($product->getTypeId() !== Type::TYPE_CODE) {
            return $data;
        }
        $groups = [];
        if ($product->getId()) {
            foreach ($this->package->getGroups((int)$product->getId()) as $group) {
                $groups[] = [
                    'record_id' => $group['group_id'],
                    'group_id' => (string)$group['group_id'],
                    'title' => $group['title'],
                    'qty' => (float)$group['qty'],
                    'is_extra' => $group['is_extra'] ? '1' : '0',
                    'position' => $group['position'],
                    'product_ids' => array_map('strval', $group['product_ids']),
                ];
            }
        }
        $data[$product->getId()][self::DATA_SCOPE] = ['is_posted' => '1', 'groups' => $groups];
        return $data;
    }

    /**
     * @inheritdoc
     */
    public function modifyMeta(array $meta)
    {
        if ($this->locator->getProduct()->getTypeId() !== Type::TYPE_CODE) {
            return $meta;
        }
        $meta[self::FIELDSET] = [
            'arguments' => ['data' => ['config' => [
                'componentType' => Fieldset::NAME,
                'label' => __('Composition %1', $this->config->getTypeLabel()),
                'collapsible' => true,
                'opened' => true,
                'dataScope' => '',
                'sortOrder' => 15,
            ]]],
            'children' => [
                'fixed_variant_info' => $this->getInfo(),
                'is_posted' => [
                    'arguments' => ['data' => ['config' => [
                        'componentType' => Field::NAME,
                        'formElement' => Hidden::NAME,
                        'dataType' => Text::NAME,
                        'dataScope' => 'data.' . self::DATA_SCOPE . '.is_posted',
                        'value' => '1',
                    ]]],
                ],
                'groups' => $this->getGroupsGrid(),
            ],
        ];
        return $meta;
    }

    /**
     * Explanation for the admin
     *
     * @return array
     */
    private function getInfo(): array
    {
        return [
            'arguments' => ['data' => ['config' => [
                'componentType' => Container::NAME,
                'component' => 'Magento_Ui/js/form/components/html',
                'additionalClasses' => 'admin__fieldset-note',
                'content' => (string)__(
                    'Add a group per kind of material, e.g. "Wool" with quantity 10 and all colours as products. '
                    . 'The customer distributes exactly that quantity over the products of the group. '
                    . 'Mark a group as "Extra" for optional spare material for beginners: '
                    . 'the quantity is then the maximum the customer can add.'
                ),
                'sortOrder' => 5,
            ]]],
        ];
    }

    /**
     * Dynamic rows with the groups
     *
     * @return array
     */
    private function getGroupsGrid(): array
    {
        return [
            'arguments' => ['data' => ['config' => [
                'componentType' => DynamicRows::NAME,
                'label' => __('Groups'),
                'addButtonLabel' => __('Add group'),
                'renderDefaultRecord' => false,
                'recordTemplate' => 'record',
                'dataScope' => 'data.' . self::DATA_SCOPE,
                'deleteProperty' => 'is_delete',
                'deleteValue' => '1',
                'dndConfig' => ['enabled' => true],
                'additionalClasses' => 'admin__field-wide netwrk-starter-groups',
                'sortOrder' => 10,
            ]]],
            'children' => [
                'record' => [
                    'arguments' => ['data' => ['config' => [
                        'componentType' => Container::NAME,
                        'isTemplate' => true,
                        'is_collection' => true,
                        'component' => 'Magento_Ui/js/dynamic-rows/record',
                        'dataScope' => '',
                        'positionProvider' => 'position',
                    ]]],
                    'children' => [
                        'title' => $this->field(__('Group title'), Input::NAME, Text::NAME, 10, [
                            'validation' => ['required-entry' => true],
                            'placeholder' => __('e.g. Wool'),
                        ]),
                        'qty' => $this->field(__('Quantity'), Input::NAME, Number::NAME, 20, [
                            'validation' => [
                                'required-entry' => true,
                                'validate-number' => true,
                                'validate-greater-than-zero' => true,
                            ],
                            'value' => '1',
                            'additionalClasses' => 'netwrk-starter-qty',
                            'tooltip' => ['description' => __('Mandatory group: exact total. Extra group: maximum.')],
                        ]),
                        'is_extra' => $this->field(__('Extra (optional)'), Checkbox::NAME, Number::NAME, 30, [
                            'prefer' => 'toggle',
                            'valueMap' => ['true' => '1', 'false' => '0'],
                            'value' => '0',
                        ]),
                        'product_ids' => $this->field(__('Products to choose from'), Select::NAME, Text::NAME, 40, [
                            'component' => 'Magento_Ui/js/form/element/ui-select',
                            'elementTmpl' => 'ui/grid/filters/elements/ui-select',
                            'options' => $this->selectableProducts->toOptionArray(),
                            'filterOptions' => true,
                            'multiple' => true,
                            'showCheckbox' => true,
                            'disableLabel' => true,
                            'chipsEnabled' => true,
                            'closeBtn' => true,
                            'validation' => ['required-entry' => true],
                        ]),
                        'group_id' => $this->field('', Hidden::NAME, Text::NAME, 80, ['visible' => false]),
                        'position' => $this->field('', Input::NAME, Number::NAME, 90, ['visible' => false]),
                        'actionDelete' => [
                            'arguments' => ['data' => ['config' => [
                                'componentType' => 'actionDelete',
                                'dataType' => Text::NAME,
                                'label' => '',
                                'fit' => true,
                                'sortOrder' => 100,
                            ]]],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Field config helper
     *
     * @param mixed $label
     * @param string $formElement
     * @param string $dataType
     * @param int $sortOrder
     * @param array $extra
     * @return array
     */
    private function field($label, string $formElement, string $dataType, int $sortOrder, array $extra = []): array
    {
        return [
            'arguments' => ['data' => ['config' => array_merge([
                'componentType' => Field::NAME,
                'formElement' => $formElement,
                'dataType' => $dataType,
                'label' => $label,
                'sortOrder' => $sortOrder,
            ], $extra)]],
        ];
    }
}
