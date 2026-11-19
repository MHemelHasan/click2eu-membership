(function () {
  "use strict";

  function fallbackCopy(value) {
    var field = document.createElement("textarea");
    field.value = value;
    field.setAttribute("readonly", "");
    field.style.position = "fixed";
    field.style.opacity = "0";
    document.body.appendChild(field);
    field.select();
    var copied = document.execCommand("copy");
    document.body.removeChild(field);
    return copied;
  }

  function copyValue(value) {
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(value).then(function () {
        return true;
      });
    }
    return Promise.resolve(fallbackCopy(value));
  }

  document.addEventListener("click", function (event) {
    var button = event.target.closest(".c2eu-payment-instructions__copy");
    if (!button) {
      return;
    }

    var value = button.getAttribute("data-copy-value") || "";
    var label = button.getAttribute("data-copy-label") || "value";
    if (!value) {
      return;
    }

    copyValue(value)
      .then(function (copied) {
        if (!copied) {
          return;
        }
        button.textContent = "Copied";
        button.setAttribute("aria-label", label + " copied");
        window.setTimeout(function () {
          button.textContent = "Copy";
          button.setAttribute("aria-label", "Copy " + label);
        }, 2000);
      })
      .catch(function () {
        // The bank value remains selectable when clipboard access is unavailable.
      });
  });
})();
