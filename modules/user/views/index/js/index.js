app.core.index = {
  modelo: function () {
    test_bd = function () {
      $.ajax({
        url: BASE_URL + "user/index/test_bd",
        dataType: 'json',
        success: function (s) {
          console.log(s)
        }
      });
    };

    // API publica
    return {
      test_bd: test_bd
    }
  },

  controlador: function () {
    app.core.index.modelo().test_bd();
  }
}

$(document).ready(function () {
  app.core.index.controlador();
}); 