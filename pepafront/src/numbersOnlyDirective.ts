'use strict';
import angular from "angular";

const numbersOnlyDirective = {
    template: "",
    controllerAs: "numbersOnly",
    bindings: {
        ngModel: '<',
    },
    restrict: "A",
    require: { modelCtrl: 'ngModel' },
    controller: ['$element', function ($element) {
        const vm = this;

        vm.fromUser = (text) => {
            if (text) {
                const transformedInput = text.replace(/[^0-9]/g, '');

                if (transformedInput !== text) {
                    vm.modelCtrl.$setViewValue(transformedInput);
                    vm.modelCtrl.$render();
                }
                return transformedInput;
            }
            return undefined;
        }

        //vm.fileinput
        vm.$onInit = () => {
            console.log('a ver que pasa');
            vm.modelCtrl.$parsers.push(vm.fromUser);

        }
    }]

};

export default numbersOnlyDirective;
