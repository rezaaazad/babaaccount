(function () {
  document.querySelectorAll(".hmr-copy").forEach(function (button) {
    button.addEventListener("click", function () {
      var id = button.getAttribute("data-copy");
      var field = id ? document.getElementById(id) : null;
      if (!field) {
        return;
      }
      field.focus();
      field.select();
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(field.value);
      }
      button.textContent = "کپی شد";
    });
  });

  document.querySelectorAll("[data-confirm]").forEach(function (button) {
    button.addEventListener("click", function (event) {
      var message = button.getAttribute("data-confirm");
      if (message && !window.confirm(message)) {
        event.preventDefault();
      }
    });
  });
})();
