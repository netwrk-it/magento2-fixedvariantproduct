/**
 * Starter package composer: counters per group, extra material toggle, total price, validation
 */
define([
    'jquery',
    'mage/translate',
    'Magento_Catalog/js/price-utils',
    'jquery-ui-modules/widget',
    'mage/validation'
], function ($, $t, priceUtils) {
    'use strict';

    var EPSILON = 0.0001;

    /**
     * Total of a group fieldset
     *
     * @param {jQuery} $group
     * @returns {Number}
     */
    function groupTotal($group) {
        var total = 0;

        $group.find('[data-role=qty]').each(function () {
            total += Math.max(0, parseFloat(this.value) || 0);
        });

        return total;
    }

    /**
     * Error message for a group or empty string when valid
     *
     * @param {jQuery} $group
     * @returns {String}
     */
    function groupError($group) {
        var required = parseFloat($group.data('required')),
            isExtra = String($group.data('extra')) === '1',
            total = groupTotal($group),
            title = $group.find('.netwrk-starter-group__title > span').first().text();

        if (isExtra) {
            return total > required + EPSILON ?
                $t('You can choose at most %1 pieces for "%2".').replace('%1', required).replace('%2', title) :
                '';
        }

        if (Math.abs(total - required) > EPSILON) {
            return $t('Please choose exactly %1 pieces for "%2" (now %3).')
                .replace('%1', required).replace('%2', title).replace('%3', total);
        }

        return '';
    }

    $.validator.addMethod(
        'netwrk-starter-group',
        function (value, element) {
            return groupError($(element).closest('[data-role=starter-group]')) === '';
        },
        function (params, element) {
            return groupError($(element).closest('[data-role=starter-group]'));
        }
    );

    $.widget('netwrk.fixedVariant', {
        options: {
            priceFormat: {},
            inputName: 'fixed_variant'
        },

        /** @inheritdoc */
        _create: function () {
            var self = this;

            this.element.find('[data-role=starter-item]').each(function () {
                var $item = $(this);

                $item.find('[data-role=item-price]').text(
                    priceUtils.formatPriceLocale(parseFloat($item.data('price')) || 0, self.options.priceFormat)
                );
            });

            this.element.on('click', '[data-role=plus], [data-role=minus]', function () {
                self._step($(this));
            });
            this.element.on('input change', '[data-role=qty]', function () {
                self._update();
            });
            this.element.on('change', '[data-role=extra-toggle]', function () {
                self._toggleExtra(this.checked);
            });

            // Pre-filled extra selection (edit from wishlist): open the extra block
            if (this.element.find('[data-role=extra-groups] [data-role=qty]').filter(function () {
                return parseFloat(this.value) > 0;
            }).length) {
                this.element.find('[data-role=extra-toggle]').prop('checked', true);
                this._toggleExtra(true);
            }

            this._update();
        },

        /**
         * Plus / minus buttons, never exceeding the group quantity
         *
         * @param {jQuery} $button
         * @private
         */
        _step: function ($button) {
            var $input = $button.closest('[data-role=starter-item]').find('[data-role=qty]'),
                $group = $button.closest('[data-role=starter-group]'),
                current = Math.max(0, parseFloat($input.val()) || 0),
                required = parseFloat($group.data('required'));

            if ($button.data('role') === 'plus') {
                if (groupTotal($group) + 1 > required + EPSILON) {
                    return;
                }
                current += 1;
            } else {
                current = Math.max(0, current - 1);
            }
            $input.val(current).trigger('change');
        },

        /**
         * Show / hide extra groups; hidden extra groups are not ordered
         *
         * @param {Boolean} show
         * @private
         */
        _toggleExtra: function (show) {
            var $extra = this.element.find('[data-role=extra-groups]');

            $extra.prop('hidden', !show);
            if (!show) {
                $extra.find('[data-role=qty]').val(0);
            }
            this._update();
        },

        /**
         * Refresh counters, plus button state and total
         *
         * @private
         */
        _update: function () {
            var self = this,
                total = 0;

            this.element.find('[data-role=starter-group]').each(function () {
                var $group = $(this),
                    chosen = groupTotal($group),
                    required = parseFloat($group.data('required')),
                    isExtra = String($group.data('extra')) === '1',
                    complete = isExtra ? chosen <= required + EPSILON : Math.abs(chosen - required) <= EPSILON;

                $group.find('[data-role=chosen]').text(chosen);
                if (complete) {
                    $group.find('[data-role=group-error] .mage-error').not('input').remove();
                }
                $group.toggleClass('is-complete', complete && chosen > 0)
                    .toggleClass('is-over', chosen > required + EPSILON);
                $group.find('[data-role=plus]').each(function () {
                    var $item = $(this).closest('[data-role=starter-item]');

                    $(this).prop('disabled', $item.hasClass('is-unavailable') || chosen + 1 > required + EPSILON);
                });

                $group.find('[data-role=starter-item]').each(function () {
                    var qty = Math.max(0, parseFloat($(this).find('[data-role=qty]').val()) || 0);

                    total += qty * (parseFloat($(this).data('price')) || 0);
                });
            });

            this.element.find('[data-role=total]').text(priceUtils.formatPriceLocale(total, self.options.priceFormat));
        }
    });

    return $.netwrk.fixedVariant;
});
