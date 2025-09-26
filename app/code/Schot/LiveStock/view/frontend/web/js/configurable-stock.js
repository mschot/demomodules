define([
    'jquery',
    'underscore'
], function ($, _) {
    'use strict';

    return function (widget) {
        $.widget('mage.configurable', widget, {
            /**
             * Initialize widget
             * @private
             */
            _create: function () {
                this._super();

                // Bind to option change events
                var self = this;

                // Listen for configurable option changes
                this.element.on('change', 'select.super-attribute-select', function () {
                    self._updateLiveStock();
                });

                this.element.on('change', 'select[data-selector^="super_attribute"]', function () {
                    self._updateLiveStock();
                });

                // Check if there's already a selected product on page load
                // This handles cases where options are pre-selected
                setTimeout(function() {
                    self._updateLiveStock();
                }, 500);
            },

            /**
             * Update live stock display based on selected product
             * @private
             */
            _updateLiveStock: function () {
                var self = this;

                // Small delay to ensure the product resolver has updated
                setTimeout(function () {
                    try {
                        var productId = null;

                        // First check if any dropdowns are empty
                        var hasEmptySelection = false;
                        var selectCount = 0;
                        self.element.find('select.super-attribute-select').each(function() {
                            var $select = $(this);
                            selectCount++;
                            var val = $select.val();
                            console.log('LiveStock: Checking dropdown', $select.attr('id'), 'value:', val, 'type:', typeof val);
                            if (!val || val === '' || val === null) {
                                hasEmptySelection = true;
                                return false; // break the loop
                            }
                        });
                        
                        console.log('LiveStock: Found', selectCount, 'dropdowns, hasEmptySelection:', hasEmptySelection);
                        
                        // If any dropdown is empty, show Select Product
                        if (hasEmptySelection) {
                            console.log('LiveStock: Empty selection detected, showing select product message');
                            $('.livestock').html($.mage.__('Availability') + ': ' + $.mage.__('Select Product')).show();
                            return;
                        }
                        
                        // Method 1: Try to get from the form's selected_configurable_option input
                        var selectedInput = self.element.find('input[name="selected_configurable_option"]');
                        if (selectedInput.length && selectedInput.val()) {
                            productId = selectedInput.val();
                            console.log('LiveStock: Got product ID from selected_configurable_option:', productId);
                        }

                        // Method 2: If that didn't work, try to calculate from selected options
                        if (!productId) {
                            var selectedOptions = {};

                            // Get all selected attribute values
                            self.element.find('select.super-attribute-select').each(function() {
                                var $select = $(this);
                                var attributeId = $select.attr('data-attribute-id') || $select.attr('id').replace('attribute', '');
                                if ($select.val() && $select.val() !== '') {
                                    selectedOptions[attributeId] = $select.val();
                                }
                            });

                            console.log('LiveStock: Selected options:', selectedOptions);

                            // Check if all required options are selected
                            var allSelected = Object.keys(selectedOptions).length > 0 &&
                                             Object.keys(selectedOptions).length === self.element.find('select.super-attribute-select').length;

                            if (allSelected && self.options.spConfig && self.options.spConfig.index) {
                                // Find the matching simple product from the index
                                var index = self.options.spConfig.index;
                                for (var pid in index) {
                                    var match = true;
                                    for (var attrId in selectedOptions) {
                                        if (!index[pid][attrId] || index[pid][attrId] != selectedOptions[attrId]) {
                                            match = false;
                                            break;
                                        }
                                    }
                                    if (match) {
                                        productId = pid;
                                        console.log('LiveStock: Found matching product ID from index:', productId);
                                        break;
                                    }
                                }
                            }
                        }

                        console.log('LiveStock: Final selected product ID:', productId);

                        if (!productId) {
                            // No simple product selected
                            console.log('LiveStock: No simple product selected, showing select product message');
                            $('.livestock').html($.mage.__('Availability') + ': ' + $.mage.__('Select Product')).show();
                            return;
                        }

                        // Use pre-loaded stock data
                        if (window.techsilLiveStock && window.techsilLiveStock.stockData) {
                            console.log('LiveStock: Using pre-loaded stock data');

                            var stockInfo = window.techsilLiveStock.stockData[productId];
                            var stockLimit = window.techsilLiveStock.stockLimit || 0;

                            if (stockInfo) {
                                var stockQty = stockInfo.qty;
                                var stockHtml = self._getStockHtml(stockQty, stockLimit);

                                console.log('LiveStock: Stock qty for product', productId, ':', stockQty);
                                $('.livestock').html(stockHtml).show();
                            } else {
                                console.log('LiveStock: No stock data found for product', productId);
                                $('.livestock').hide();
                            }
                        } else {
                            console.log('LiveStock: No pre-loaded stock data available');
                            $('.livestock').hide();
                        }
                    } catch (e) {
                        console.error('LiveStock: Error updating stock:', e);
                    }
                }, 100);
            },

            /**
             * Generate stock HTML
             * @private
             * @param {number} stockQty
             * @param {number} stockLimit
             * @return {string}
             */
            _getStockHtml: function(stockQty, stockLimit) {
                var html = $.mage.__('Availability') + ': ';

                if (stockQty > stockLimit) {
                    html += stockLimit + $.mage.__('+ in stock');
                } else if (stockQty < 1) {
                    html += $.mage.__('Contact us');
                } else {
                    html += stockQty + ' ' + $.mage.__('in stock');
                }

                return html;
            }
        });

        return $.mage.configurable;
    };
});