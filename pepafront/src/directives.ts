'use strict';

import moment = require("moment");
import angular from "angular";


angular.module('appDirectives', [])

    .directive('dtcanceladialogo', ['ModalService', '$state', function (ModalService, $state) {
        function doCancel(form) {
            // Clear form and explicitly reset $dirty flag to avoid dirty propagation to parent scope.
            form.$setPristine();
            form.$setUntouched();
            $state.go('.', { action: 'lista' });
        }

        return {
            restrict: 'A',
            require: ['^form'],
            scope: {
                //         formname: "@canceladialogo",
                //         twoWayBind: "=myTwoWayBind",
                //         oneWayBind: "&myOneWayBind"
            },
            link(scope, element, attrs, ctrls) {
                element.on('click', function () {
                    const form = ctrls[0];
                    if (form.$dirty) {
                        ModalService.confirmaCancela('¿Seguro desea descartar los cambios y cancelar?', 'Cancelar Confirmación')
                            .then(function () {
                                doCancel(form);
                            }).catch(function () { });
                    } else {  // Cierro de una
                        doCancel(form);
                    }
                });
            },
        };
    }])

    .directive('dtcancela', ['ModalService', '$state', function (ModalService, $state) {
        function doCancel(form, scope) {
            form.$setPristine();
            form.$setUntouched();
            $state.go('.', { action: 'lista' });
        }

        return {
            restrict: 'A',
            require: ['^form'],
            scope: {
                hideTab: '=',
            },
            link(scope: any, element, attrs, ctrls) {
                element.on('click', function (event) {
                    const form = ctrls[0];
                    if (form.$dirty) {
                        ModalService.confirmaCancela('¿Seguro desea descartar los cambios y cancelar?', 'Cancelar Confirmación')
                            .then(function () {
                                scope.hideTab = true;
                                doCancel(form, scope);
                            }).catch(function () { });
                    } else {
                        // Cierro de una
                        scope.hideTab = true;
                        doCancel(form, scope);
                    }
                });
            },
        };
    }])

    .directive('optionsClass', ['$parse', function ($parse) {
        return {
            // require: ['select','option'],
            link(scope: any, elem, attrs) {
                if (elem[0].tagName === 'SELECT') {
                    // get the source for items array that populates the select.
                    const optionsSourceStr = attrs.ngOptions.split(' ').pop(),
                        // use $parse to get a function from options-class attribute.
                        getOptionsClass = $parse(attrs.optionsClass);

                    scope.$watch(optionsSourceStr, function (items) {
                        // when the options source changes loop through its items.
                        angular.forEach(items, function (item, index) {
                            // evaluate against the item to get a mapping object for classes.
                            const classes = getOptionsClass(item);

                            // get option by looking for appropriate index in value attribute.
                            // var option = elem.find('option[value=' + * + ']'); //Not work.
                            const option = elem.children()[index];

                            // loop through the key/value pairs in mapping object and conditinally apply classes.
                            // use Array.some for breaking loop after matching.
                            // classes.some(function (type, className) {});
                            // But need iterate all possible classes...
                            angular.forEach(classes, function (type, className: string) {
                                if ((type === 'placeholder' && index === 0) ||
                                    (type !== 'placeholder' && index > 0)) {
                                    angular.element(option).addClass(className);
                                }
                            });
                        });
                    });
                } else if (elem[0].tagName === 'OPTION') {
                    // Used if placeholder item is included in server data return.
                    const getOptionsClass = $parse(attrs.optionsClass);
                    const classes = getOptionsClass();
                    angular.forEach(classes, function (type, className: string) {
                        if ((type === 'placeholder' && elem[0].parentElement.children.length === 1) ||
                            (type !== 'placeholder' && elem[0].parentElement.children.length > 1)) {
                            angular.element(elem).addClass(className);
                        }
                    });
                }
            },
        };
    }])
    .directive('optionsClass', ['$parse', function ($parse) {
        return {
            // require: ['select','option'],
            link(scope: any, elem, attrs) {
                if (elem[0].tagName === 'SELECT') {
                    // get the source for items array that populates the select.
                    const optionsSourceStr = attrs.ngOptions.split(' ').pop(),
                        // use $parse to get a function from options-class attribute.
                        getOptionsClass = $parse(attrs.optionsClass);

                    scope.$watch(optionsSourceStr, function (items) {
                        // when the options source changes loop through its items.
                        angular.forEach(items, function (item, index) {
                            // evaluate against the item to get a mapping object for classes.
                            const classes = getOptionsClass(item);

                            // get option by looking for appropriate index in value attribute.
                            // var option = elem.find('option[value=' + * + ']'); //Not work.
                            const option = elem.children()[index];

                            // loop through the key/value pairs in mapping object and conditinally apply classes.
                            // use Array.some for breaking loop after matching.
                            // classes.some(function (type, className) {});
                            // But need iterate all possible classes...
                            angular.forEach(classes, function (type, className: string) {
                                if ((type === 'placeholder' && index === 0) ||
                                    (type !== 'placeholder' && index > 0)) {
                                    angular.element(option).addClass(className);
                                }
                            });
                        });
                    });
                } else if (elem[0].tagName === 'OPTION') {
                    // Used if placeholder item is included in server data return.
                    const getOptionsClass = $parse(attrs.optionsClass);
                    const classes = getOptionsClass();
                    angular.forEach(classes, function (type, className: string) {
                        if ((type === 'placeholder' && elem[0].parentElement.children.length === 1) ||
                            (type !== 'placeholder' && elem[0].parentElement.children.length > 1)) {
                            angular.element(elem).addClass(className);
                        }
                    });
                }
            },
        };
    }])

    .directive('clickAndDisable', function () {
        return {
            scope: {
                clickAndDisable: '&',
            },
            link(scope: any, ele, iAttrs) {
                ele.bind('click', function () {
                    if (ele.attr('disabled'))
                        return;
                    ele.attr('disabled', 'disabled');
                    const deferred = scope.clickAndDisable();
                    if (angular.isObject(deferred)) {
                        deferred
                            .then(function () {
                                
                            })
                            .catch(function () { 

                            })
                            .finally(function () {
                            ele.removeAttr('disabled');
                        });
                    } else {
                        ele.removeAttr('disabled');
                    }
                });
                /*
                                ele.parent().bind("keydown keypress", function (event) {
                                    if (event.which === 13) {
                                        scope.$apply(function () {
                                            console.log('presiona enter');
                                        });
                        
                                        event.preventDefault();
                                    }
                                });
                */

            },
        };
    })
    
    .directive('numbersOnly', function () {
        return {
            require: 'ngModel',
            link(scope, element, attr, ngModelCtrl) {
                function fromUser(text) {
                    if (text) {
                        const transformedInput = text.replace(/[^0-9]/g, '');

                        if (transformedInput !== text) {
                            ngModelCtrl.$setViewValue(transformedInput);
                            ngModelCtrl.$render();
                        }
                        return transformedInput;
                    }
                    return undefined;
                }
                ngModelCtrl.$parsers.push(fromUser);
            },
        };
    })
    // LOG
    .directive('expandOnFocus', function () {
        return {
            restrict: 'A',
            link(scope, elem, attrs) {
                let expandTo = attrs.expandOnFocus;
                if (expandTo[expandTo.length] !== 'x') {
                    expandTo += 'px';
                }

                let original = null;
                elem.bind('focus', function () {
                    original = elem[0].offsetWidth;
                    elem.css('width', expandTo);
                });
                elem.bind('blur', function () {
                    if (original) {
                        elem.css('width', original + 'px');
                    }
                });
            },
        };
    })





    .directive('ignoreDirty', [function () {
        return {
            require: 'ngModel',
            restrict: 'A',
            link(scope, elm, attrs, ctrl) {
                ctrl.$setPristine = function () { };
                ctrl.$pristine = false;
            },
        };
    }])

    .directive('fmtdatetimesql', ['cfg', function (cfg) {
        return {
            require: 'ngModel',
            link: function (scope, element, attrs, ngModelController) {

                ngModelController.$parsers.push(function (data) {
                    if (!data)
                        return "";
                    if (attrs.type == "date")
                        return (moment(data).format(cfg.datetimeformatmodelMoment));
                    if (attrs.type == "datetime-local")
                        return (moment(data).utc().format(cfg.datetimeformatmodelMoment));
                });

                ngModelController.$formatters.push(function (data) {
                    if (!data) {
                        return null;
                    }
                    if (attrs.type == "date")
                        return (moment(data, 'YYYY-MM-DD HH:mm:ss.SSS').toDate());
                    if (attrs.type == "datetime-local")
                        return (moment.utc(data, 'YYYY-MM-DD HH:mm:ss.SSS').local().toDate());

                });
            }
        };
    }])
    .directive('formatted', ['$filter', '$injector', function ($filter, $injector) {
        return {
            require: 'ngModel',
            link(scope, elem, attrs, ctrl) {
                if (!ctrl) {
                    return;
                }
                // convert data from model format to view format
                ctrl.$formatters.unshift(function (a) {
                    return $filter(attrs.formatted)(ctrl.$modelValue);
                });
                // convert data from view format to model format
                ctrl.$parsers.unshift(function (viewValue) {
                    elem.val($filter(attrs.formatted)(viewValue));
                    let filter = attrs.formatted;
                    if ($injector.has(filter + 'InversaFilter')) {
                        filter += 'Inversa';
                    }
                    return $filter(filter)(viewValue);
                });
            },
        };
    }])

    .directive('onError', function () {
        return {
            restrict: 'A',
            link: function (scope, element, attr) {
                element.on('error', function () {
                    element.attr('src', attr.onError);
                })
            }
        }
    })

    .directive('uiHideChoices', [function () {
        return {
            require: 'ngModel',
            restrict: 'A',
            link(scope, elem, attrs, ctrl) {
                scope.$watch(function () { return elem.find('.ui-select-choices-row').hasClass('disabled'); }, function (newValue) {
                    elem.find('.disabled').attr('hidden', 'true');
                });
            },
        };
    }])
/*
    .directive('inputxx', function () {
        return {
            restrict: 'E',
            scope: {
                ngModel: '=',
                ngChange: '&',
                type: '@'
            },
            link: function (scope, element, attrs) {
                if (scope.type.toLowerCase() != 'file') {
                    return;
                }
                element.bind('change', function () {
                    let files = element[0].files;
                    scope.ngModel = files;
                    scope.$apply();
                    scope.ngChange();
                });
            }
        }
    })
*/
    ;
