'use strict';
import angular from "angular";

const adjuntarImgComponent = {
    template: require('../Pages/Templates/adjuntar_template.html'),
    controllerAs: "adjuntarImg",
    bindings: {
        ngModel: '<',
        callback: '&',
    },
    require: { modelCtrl: 'ngModel' },
    controller: ['$element', function ($element) {
        const vm = this;

        vm.setImgEmpty = () => {
            if (vm.stillimg)
                vm.stillimg.src = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
        }

        //vm.fileinput
        vm.$onInit =  () => {
            vm.stillimg = $element.find('img')[0];
            vm.setImgEmpty();

            $element.bind('change', function (changeEvent: any) {
                vm.fileinput = changeEvent.target.files[0];
                const reader = new FileReader();
                reader.onload = function (loadEvent) {
                    vm.modelCtrl.$setViewValue(loadEvent.target.result);
                    vm.modelCtrl.$render();
                    vm.stillimg.src = loadEvent.target.result;
                };
                reader.readAsDataURL(vm.fileinput);
            });
    
        }

        vm.$onChanges = (changes) => {
            if (changes.ngModel) {
                if (changes.ngModel.currentValue) {
                    vm.stillimg.src = changes.ngModel.currentValue;
                } else {
                    vm.setImgEmpty();
                }
            }
        }

        vm.borrarContenido = () => {
            const eleInput = $element.find('input');
            eleInput.val('');

            if (vm.placeholder) {
                const placeholder = document.createElement('img');
                placeholder.setAttribute('class', 'webcam-loader');
                placeholder.src = vm.placeholder;
                $element.append(placeholder);

            }
            vm.modelCtrl.$setViewValue('');
            vm.modelCtrl.$render();
        };

        vm.adjuntar =  () => {
            setTimeout(function () {
                (<HTMLInputElement>$element.find('input')[0]).click();
            }, 0);
        };

    }]
};

export default adjuntarImgComponent;
