'use strict';
const movisucesosComponent =
{
  template: require('../Pages/movisucesos.html'),
  bindings: {
  },
  controllerAs: "movisucesos",
  controller: ['$timeout', 'datosBack','$translate', function ($timeout, datosBack,$translate) {

    var vm = this;
    vm.form_title = $translate.instant("Sucesos");
    vm.suceso = {};
    


    vm.$onInit = function () {
      $timeout(function () { vm.grilla.fillGrid() },0);
    };

    //Set search items for starting and reset.
    vm.gridOptions = {
      excludeProperties: '__metadata',
      enablePaginationControls: false,
      enableRowSelection: true,
      enableRowHeaderSelection: true,
      multiSelect: false,
      enableFullRowSelection: true,
      useExternalSorting: true,
      data: []
      //multiSelect: false
    };

    vm.exportar = function (tipo) {
      datosBack.export(vm.grilla.getLoadOptions(), tipo).then(function (response) { }).catch(function (data) { });
    };
  }]
};
export default movisucesosComponent;