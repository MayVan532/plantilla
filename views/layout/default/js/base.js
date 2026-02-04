var app = { clase: {}, core: {}, globales: {} }
app.globales.maxuploadsize = 20024000;

$(document).ready(function () {
  $.ajaxSetup({
    headers:
      { 'X-CSRF-TOKEN': TOKEN_CSRF }
  });
});