// This file is part of Moodle - http://moodle.org/

/**
 * Chart.js integration for block_completion_stats.
 *
 * @module     block_completion_stats/chart
 * @copyright  2025, Yasiru Navoda Jayasekara
 */

define(['jquery', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js'],
    function ($, Chart) {

        return {
            /**
             * Initialize the chart.
             *
             * @param {Object} data - The completion statistics data
             */
            init: function (data) {
                $(document).ready(function () {
                    var ctx = document.getElementById(data.chartid);

                    if (!ctx) {
                        return;
                    }

                    // Create pie chart - Completed vs Not Completed
                    new Chart(ctx, {
                        type: 'pie',
                        data: {
                            labels: ['Completed', 'Not Completed'],
                            datasets: [{
                                data: [
                                    data.completed,
                                    data.remaining
                                ],
                                backgroundColor: [
                                    '#28a745',  // Green for completed
                                    '#6c757d'   // Gray for not completed
                                ],
                                borderColor: [
                                    '#ffffff',
                                    '#ffffff'
                                ],
                                borderWidth: 2
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        padding: 10,
                                        font: {
                                            size: 12,
                                            weight: 'bold'
                                        },
                                        usePointStyle: true,
                                        pointStyle: 'circle'
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function (context) {
                                            var label = context.label || '';
                                            var value = context.parsed || 0;
                                            var total = data.total;
                                            var percent = ((value / total) * 100).toFixed(1);
                                            return label + ': ' + value + ' (' + percent + '%)';
                                        }
                                    }
                                }
                            }
                        }
                    });
                });
            }
        };
    });
