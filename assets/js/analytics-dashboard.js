/**
 * Mix & Match Bundle - Analytics Dashboard JavaScript
 * Handles chart rendering, data updates, and interactions
 */

(function($) {
    'use strict';

    // Chart instances storage
    const chartInstances = {};

    // Chart color schemes
    const colorSchemes = {
        primary: ['#3b82f6', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981'],
        gradient: [
            'rgba(59, 130, 246, 0.8)',
            'rgba(139, 92, 246, 0.8)',
            'rgba(236, 72, 153, 0.8)',
            'rgba(245, 158, 11, 0.8)',
            'rgba(16, 185, 129, 0.8)'
        ],
        success: '#10b981',
        warning: '#f59e0b',
        error: '#ef4444',
        info: '#3b82f6'
    };

    // Initialize dashboard
    const MMBAnalytics = {
        init: function() {
            // Debug: Log analytics data
            if (typeof window.mmbAnalyticsData !== 'undefined') {
                console.log('MMB Analytics Data:', window.mmbAnalyticsData);
            } else {
                console.error('MMB Analytics Data not found!');
            }

            // Debug: Check if Chart.js is available
            console.log('MMB: Chart.js available?', typeof Chart !== 'undefined');
            if (typeof Chart !== 'undefined') {
                console.log('MMB: Chart.js version:', Chart.version || 'unknown');
            }

            // Wait a bit for Chart.js to be fully loaded if needed
            if (typeof Chart === 'undefined') {
                // Try again after a short delay
                setTimeout(() => {
                    this.initCharts();
                }, 100);
            } else {
                this.initCharts();
            }
            
            this.initDateFilters();
            this.initRefreshButton();
            this.initTooltips();
        },

        /**
         * Initialize all charts
         */
        initCharts: function() {
            // Wait for Chart.js to load if it's not immediately available
            if (typeof Chart === 'undefined') {
                console.warn('Chart.js not loaded, waiting for it...');
                
                // Try to wait for Chart.js to load (in case it's loading asynchronously)
                let attempts = 0;
                const maxAttempts = 10;
                const checkChart = setInterval(() => {
                    attempts++;
                    if (typeof Chart !== 'undefined') {
                        clearInterval(checkChart);
                        console.log('Chart.js loaded, initializing charts...');
                        this.initCharts();
                    } else if (attempts >= maxAttempts) {
                        clearInterval(checkChart);
                        console.error('Chart.js failed to load after multiple attempts. Please check if the file exists at: assets/js/vendor/chart.umd.min.js');
                    }
                }, 100);
                
                return;
            }

            // Set global chart defaults
            Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
            Chart.defaults.color = '#64748b';
            Chart.defaults.plugins.legend.display = true;
            Chart.defaults.plugins.legend.position = 'bottom';

            // Initialize each chart (only if canvas elements exist)
            this.initCouponUsageChart();
            this.initBundleCreationChart();
            this.initRevenueChart();
            this.initCartAnalyticsChart();
            // Only initialize conversion chart if the canvas exists
            if (document.getElementById('mmb-conversion-chart')) {
                this.initConversionChart();
            }
            this.initBundlePerformanceChart();
        },

        /**
         * Coupon Usage Doughnut Chart
         */
        initCouponUsageChart: function() {
            const ctx = document.getElementById('mmb-coupon-chart');
            if (!ctx) {
                // Silently return if canvas doesn't exist
                return;
            }

            try {
                const data = {
                    labels: window.mmbAnalyticsData?.couponLabels || ['Used', 'Unused'],
                    datasets: [{
                        data: window.mmbAnalyticsData?.couponData || [0, 0],
                        backgroundColor: [colorSchemes.success, colorSchemes.error],
                        borderWidth: 0,
                        hoverOffset: 10
                    }]
                };

                console.log('Coupon Chart Data:', data);

                chartInstances.couponUsage = new Chart(ctx, {
                type: 'doughnut',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                usePointStyle: true,
                                font: {
                                    size: 13,
                                    weight: '500'
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            cornerRadius: 8,
                            titleFont: {
                                size: 14,
                                weight: '600'
                            },
                            bodyFont: {
                                size: 13
                            },
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
            } catch (error) {
                console.error('Error initializing coupon chart:', error);
            }
        },

        /**
         * Bundle Creation Line Chart
         */
        initBundleCreationChart: function() {
            const ctx = document.getElementById('mmb-bundle-chart');
            if (!ctx) {
                // Silently return if canvas doesn't exist
                return;
            }

            try {
                const data = {
                labels: window.mmbAnalyticsData?.bundleLabels || [],
                datasets: [{
                    label: 'Bundles Created',
                    data: window.mmbAnalyticsData?.bundleData || [],
                    borderColor: colorSchemes.primary[0],
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    pointBackgroundColor: '#fff',
                    pointBorderWidth: 2
                }]
            };

            chartInstances.bundleCreation = new Chart(ctx, {
                type: 'line',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            cornerRadius: 8
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            },
                            ticks: {
                                precision: 0
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
            } catch (error) {
                console.error('Error initializing bundle chart:', error);
            }
        },

        /**
         * Revenue Bar Chart
         */
        initRevenueChart: function() {
            const ctx = document.getElementById('mmb-revenue-chart');
            if (!ctx) {
                // Silently return if canvas doesn't exist
                return;
            }

            try {

            const data = {
                labels: window.mmbAnalyticsData?.revenueLabels || [],
                datasets: [{
                    label: 'Revenue',
                    data: window.mmbAnalyticsData?.revenueData || [],
                    backgroundColor: colorSchemes.gradient,
                    borderRadius: 8,
                    borderSkipped: false
                }]
            };

            chartInstances.revenue = new Chart(ctx, {
                type: 'bar',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            cornerRadius: 8,
                            callbacks: {
                                label: function(context) {
                                    return 'Revenue: $' + context.parsed.y.toFixed(2);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toLocaleString();
                                }
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
            } catch (error) {
                console.error('Error initializing revenue chart:', error);
            }
        },

        /**
         * Cart Analytics Chart
         */
        initCartAnalyticsChart: function() {
            const ctx = document.getElementById('mmb-cart-chart');
            if (!ctx) {
                // Silently return if canvas doesn't exist
                return;
            }

            try {

            const data = {
                labels: window.mmbAnalyticsData?.cartLabels || ['Total Carts', 'With Bundles'],
                datasets: [{
                    data: window.mmbAnalyticsData?.cartData || [0, 0],
                    backgroundColor: [colorSchemes.info, colorSchemes.success],
                    borderWidth: 0
                }]
            };

            chartInstances.cartAnalytics = new Chart(ctx, {
                type: 'doughnut',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                usePointStyle: true
                            }
                        }
                    }
                }
            });
            } catch (error) {
                console.error('Error initializing cart chart:', error);
            }
        },

        /**
         * Conversion Rate Line Chart
         */
        initConversionChart: function() {
            const ctx = document.getElementById('mmb-conversion-chart');
            if (!ctx) {
                // Silently return if canvas doesn't exist (chart might not be in template)
                return;
            }

            try {

            const data = {
                labels: window.mmbAnalyticsData?.conversionLabels || [],
                datasets: [{
                    label: 'Conversion Rate (%)',
                    data: window.mmbAnalyticsData?.conversionData || [],
                    borderColor: colorSchemes.primary[1],
                    backgroundColor: 'rgba(139, 92, 246, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            };

            chartInstances.conversion = new Chart(ctx, {
                type: 'line',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        }
                    }
                }
            });
            } catch (error) {
                console.error('Error initializing conversion chart:', error);
            }
        },

        /**
         * Bundle Performance Horizontal Bar Chart
         */
        initBundlePerformanceChart: function() {
            const ctx = document.getElementById('mmb-bundle-performance-chart');
            if (!ctx) {
                // Silently return if canvas doesn't exist
                return;
            }

            try{

            const data = {
                labels: window.mmbAnalyticsData?.bundlePerformanceLabels || [],
                datasets: [{
                    label: 'Usage Count',
                    data: window.mmbAnalyticsData?.bundlePerformanceData || [],
                    backgroundColor: colorSchemes.gradient,
                    borderRadius: 8
                }]
            };

            chartInstances.bundlePerformance = new Chart(ctx, {
                type: 'bar',
                data: data,
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        y: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
            } catch (error) {
                console.error('Error initializing bundle performance chart:', error);
            }
        },

        /**
         * Initialize date filter functionality
         */
        initDateFilters: function() {
            const $dateRange = $('#mmb-date-range');
            const $customDates = $('#mmb-custom-dates');
            const $applyFilter = $('#mmb-apply-filter');

            // Show/hide custom date inputs
            $dateRange.on('change', function() {
                if ($(this).val() === 'custom') {
                    $customDates.slideDown(200);
                } else {
                    $customDates.slideUp(200);
                }
            });

            // Apply filter button
            $applyFilter.on('click', function(e) {
                e.preventDefault();
                MMBAnalytics.applyDateFilter();
            });

            // Allow Enter key to submit
            $('#mmb-start-date, #mmb-end-date').on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    MMBAnalytics.applyDateFilter();
                }
            });
        },

        /**
         * Apply date filter and reload data
         */
        applyDateFilter: function() {
            const dateRange = $('#mmb-date-range').val();
            const startDate = $('#mmb-start-date').val();
            const endDate = $('#mmb-end-date').val();
            const nonce = $('#mmb-analytics-nonce').val();

            // Build URL with parameters
            const params = new URLSearchParams({
                page: 'mmb-analytics',
                date_range: dateRange
            });

            if (dateRange === 'custom' && startDate && endDate) {
                params.append('start_date', startDate);
                params.append('end_date', endDate);
            }
            if (nonce) {
                params.append('mmb_analytics_nonce', nonce);
            }

            // Reload page with new parameters
            window.location.href = 'admin.php?' + params.toString();
        },


        /**
         * Initialize refresh button
         */
        initRefreshButton: function() {
            $('.mmb-refresh-data').on('click', function(e) {
                e.preventDefault();
                location.reload();
            });
        },

        /**
         * Initialize tooltips
         */
        initTooltips: function() {
            // Add tooltips to elements with data-tooltip attribute
            $('[data-tooltip]').each(function() {
                $(this).attr('title', $(this).data('tooltip'));
            });
        },

        /**
         * Update chart data dynamically
         */
        updateChartData: function(chartName, newData) {
            if (chartInstances[chartName]) {
                chartInstances[chartName].data = newData;
                chartInstances[chartName].update();
            }
        },

        /**
         * Destroy all charts (for cleanup)
         */
        destroyCharts: function() {
            Object.keys(chartInstances).forEach(function(key) {
                if (chartInstances[key]) {
                    chartInstances[key].destroy();
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        MMBAnalytics.init();
    });

    // Expose to global scope for external access
    window.MMBAnalytics = MMBAnalytics;

})(jQuery);

