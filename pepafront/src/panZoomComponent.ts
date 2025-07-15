'use strict';
import angular, { isNumber } from "angular";
import createPanZoom from "panzoom";
const panZoomComponent = {
    transclude: true,
    template: '<p class="pz-info" ng-if="ctrl.imageLoaded">dimensiones: {{ ctrl.size }} px <BR> zoom: {{ctrl.scale | number : 2 }} X <BR> tamaño: {{ctrl.filesizeMB | number : 2 }} MBytes</p> <div class="pz-ele"><img class="pz-img" src="{{ ctrl.ngModel }}" ng-on-error="ctrl.loaderror()" ng-on-load="ctrl.load()" /><div ng-transclude/></div>',
    controllerAs: "ctrl",
    require: {
        ngModelCtrl: "ngModel"
    },
    bindings: {
        ngModel: '=',
//        temas: '=',
//        _temaPopUp: '&temaPopUp'

    },
    controller: ['$element', '$scope', '$window', '$timeout', function ($element, $scope, $window, $timeout) {
        const vm = this;

        const panzoom_el = $element.find(".pz-ele");
        const img_el = $element.find(".pz-img");
        panzoom_el.addClass('pz-outer-element');

        vm.timer1 = '';
        vm.size = '0x0';
        vm.scale = 0;

        vm.isVisible = false;
        vm.htmliconos = "";
        vm.imageLoaded = false;
        $scope.$watch(function () { return panzoom_el.is(':visible') }, function (newValue, oldValue) {
            vm.isVisible = newValue;
            vm.fitContainer(vm.ptz, panzoom_el);

        });

/*
        vm.temaPopUp = (cod_tema: string) => {
            vm._temaPopUp({ "cod_tema": cod_tema });
        }
*/
        vm.load = () => {

            vm.size = img_el[0].naturalWidth + 'x' + img_el[0].naturalHeight;
            if (img_el[0].clientWidth == 0)
                img_el[0].width = img_el[0].naturalWidth
            if (img_el[0].clientHeight == 0)
                img_el[0].height = img_el[0].naturalHeight
            
            vm.filesizeMB = vm.ngModel.length / 1000000;
            vm.fitContainer(vm.ptz, panzoom_el);
            vm.imageLoaded = true;
        }

        vm.loaderror = () => {
            vm.imageLoaded = false;
            //                console.log('loaderror');
        }

        vm.fitContainer = (ptz, element) => {
            if (!vm.isVisible) return;

            //Bug: Ojo element a veces es disto a children en H?
            const parentW = element.parent()[0].clientWidth - 10;
            const parentH = element.parent()[0].clientHeight - 10;
            const clientW = element[0].clientWidth;
            const clientH = element[0].clientHeight;
            const scale = Math.min(parentW / clientW, parentH / clientH);
            if (scale > 0)
                ptz.zoomAbs(0, 0, scale);
        };

        vm.resizeWindow = () => {
            if (!vm.isVisible) return;
            $timeout.cancel(vm.timer1);
            vm.timer1 = $timeout(function () {
                vm.fitContainer(vm.ptz, panzoom_el);
            }, 200);
        }

        vm.ptz = createPanZoom(panzoom_el[0], {
            smoothScroll: true,
            transformOrigin: { x: 0.5, y: 0.5 },

        });

        vm.ptz.on('transform', (e) => {
            const transformObject = e.getTransform();
            vm.scale = transformObject.scale;
            vm.debug = transformObject;
            const mapWrap = panzoom_el.parent();
            var trig = false;

            var newX = transformObject.x;
            var newY = transformObject.y;

            const qX = -panzoom_el[0].offsetWidth * transformObject.scale + mapWrap[0].offsetWidth - 10;
            const qY = -panzoom_el[0].offsetHeight * transformObject.scale + mapWrap[0].offsetHeight - 10;
            var virtualX = (qX > 0) ? qX / 2 : 0;
            var virtualY = (qY > 0) ? qY / 2 : 0;




            if (newX > virtualX) {
                newX = virtualX;
                trig = true;
            }

            if (newY > virtualY) {
                newY = virtualY;
                trig = true;
            }

            if (qX < 0 && transformObject.x < qX) {  //Proceso X
                newX = qX;
                trig = true;
            }

            if (qY < 0 && transformObject.y < qY) {  //Proceso Y
                newY = qY;
                trig = true;
            }

            if (qX >= 0 && newX != virtualX) {
                newX = virtualX;
                trig = true;
            }

            if (qY >= 0 && newY != virtualY) {
                newY = virtualY;
                trig = true;
            }
            if (trig)
                e.moveTo(newX, newY);

        });

        vm.$onInit = function () {
            angular.element($window).on('resize', vm.resizeWindow);
        };

        $scope.$on('$destroy', function () {

            angular.element($window).off('resize', vm.resizeWindow);
        });

    }],

};

export default panZoomComponent;