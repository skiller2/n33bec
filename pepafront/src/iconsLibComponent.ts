'use strict';

const csIconComponent = {
    transclude: true,
    template: '<div class="icon-lib {{ $ctrl.animate }}"></div><div ng-transclude></div>',
    bindings: {
        symbol: '=',
        ngTouchstart: '&',
        ngMove: '&',
        allowMove: '<',
        animate:'<'
    },
    controllerAs: "$ctrl",
    controller: ['$element', '$document', '$window', '$timeout', 'datosBack', '$attrs', 'detalletemas', '$sce', function ($element, $document, $window, $timeout, datosBack, $attrs, detalletemas, $sce) {
        const vm = this;
        vm.symbolold = "";
        $element[0].style.display = 'inline-block';
        $element[0].style.lineHeight = '0px';

        vm.$onInit = () => {
            $element.on('touchstart', function () {
                vm.ngTouchstart();
            });

            var startX, startY, initialMouseX, initialMouseY, scale, dy, dx, left, top;

            if (vm.allowMove == true) {
                $element.bind('mousedown', function ($event) {
                    const parentW = $element.parent().parent().parent()[0].clientWidth - 10;
                    const parentH = $element.parent().parent().parent()[0].clientHeight - 10;
                    const clientW = $element.parent().parent()[0].clientWidth;
                    const clientH = $element.parent().parent()[0].clientHeight;
                    scale = Math.min(parentW / clientW, parentH / clientH);

                    startX = $element.prop('offsetLeft');
                    startY = $element.prop('offsetTop');
                    initialMouseX = $event.clientX;
                    initialMouseY = $event.clientY;

                    $element.parent().parent().bind('mousemove', mousemove);
                    $element.parent().parent().bind('mouseup', mouseup);
                    return false;
                });
            }

            function mousemove($event) {
                dx = $event.clientX - initialMouseX;
                dy = $event.clientY - initialMouseY;
                top = Math.round(startY + dy / scale);
                left = Math.round(startX + dx / scale);
                $element.css({
                    top: top + 'px',
                    left: left + 'px'
                });
                return false;
            }

            function mouseup() {
                vm.ngMove({ event: { left: left, top: top } });
                $element.parent().parent().unbind('mousemove', mousemove);
                $element.parent().parent().unbind('mouseup', mouseup);
            }

        }

        vm.$onDestroy = () => {
            $element.off('touchstart');
        }
        vm.$doCheck = () => {

            if (vm.symbol != vm.symbolold) {
                vm.symbolold = vm.symbol;
                if (vm.symbol) {
                    let html = "";
                    if (vm.symbol.indexOf('cs-') == 0)
                        html = '<svg role="img" class="cs-icon ' + vm.symbol + ' " ><use href="#' + vm.symbol + '"/></svg>';

                    if (vm.symbol.indexOf('fa') == 0)
                        html = '<i class="' + vm.symbol + '" />';
                    $element.find(".icon-lib").html(html);
                }
//                vm.csiconhtml = html;
            }
        }
    }],
};

export default csIconComponent;
