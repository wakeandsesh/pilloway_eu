(function ($) {
  "use strict";

  var syncing = false;
  var arrangeQueued = false;
  var initialCheckoutUpdated = false;
  var initialNovaRefreshQueued = false;
  var initialNovaRefreshSent = false;

  function selectedRate() {
    var selected = document.querySelector('input[name^="shipping_method"]:checked');
    return selected ? selected.value : "";
  }

  function queueInitialNovaStateRefresh() {
    if (
      !initialCheckoutUpdated ||
      initialNovaRefreshQueued ||
      initialNovaRefreshSent ||
      selectedRate().indexOf("nova_post_division:") !== 0
    ) {
      return;
    }

    var root = document.getElementById("nova-post-widget-root");
    if (!root) return;

    initialNovaRefreshQueued = true;
    window.requestAnimationFrame(function () {
      window.requestAnimationFrame(function () {
        initialNovaRefreshQueued = false;

        if (
          initialNovaRefreshSent ||
          selectedRate().indexOf("nova_post_division:") !== 0
        ) {
          return;
        }

        var currentRoot = document.getElementById("nova-post-widget-root");
        if (!currentRoot || currentRoot.querySelector(".nova-post-widget")) return;

        initialNovaRefreshSent = true;
        $(document.body).trigger("update_checkout");
      });
    });
  }
  function copyValue(sourceId, targetId) {
    var source = document.getElementById(sourceId);
    var target = document.getElementById(targetId);
    if (!source || !target || target.value === source.value) return;
    target.value = source.value;
    $(target).trigger("change");
  }

  function updateDeliveryFields() {
    if (syncing) return;

    var rate = selectedRate();
    var isNovaAddress = rate.indexOf("nova_post_address:") === 0;
    var isNovaDivision = rate.indexOf("nova_post_division:") === 0;
    var body = document.body;
    var checkbox = document.getElementById("ship-to-different-address-checkbox");

    body.classList.toggle("pilloway-nova-post-address", isNovaAddress);
    body.classList.toggle("pilloway-nova-post-division", isNovaDivision);
    if (!checkbox) return;

    syncing = true;
    if (isNovaAddress) {
      copyValue("billing_first_name", "shipping_first_name");
      copyValue("billing_last_name", "shipping_last_name");
      copyValue("billing_country", "shipping_country");
      if (!checkbox.checked) {
        checkbox.checked = true;
        $(checkbox).trigger("change");
      }
    } else if (isNovaDivision && checkbox.checked) {
      checkbox.checked = false;
      $(checkbox).trigger("change");
    }
    syncing = false;
  }

  function makeElement(tag, className) {
    var element = document.createElement(tag);
    if (className) element.className = className;
    return element;
  }

  function moveTo(node, parent) {
    if (node && parent && node.parentElement !== parent) {
      parent.appendChild(node);
    }
  }

  function ensureChildOrder(parent, nodes) {
    var cursor = parent.firstElementChild;
    nodes.forEach(function (node) {
      if (!node) return;
      if (node !== cursor) {
        parent.insertBefore(node, cursor);
      }
      cursor = node.nextElementSibling;
    });
  }

  function createLayout(form) {
    var layout = form.querySelector(".pilloway-checkout-layout");
    if (layout) return layout;

    layout = makeElement("div", "pilloway-checkout-layout");
    var left = makeElement("div", "pilloway-checkout-left");
    var right = makeElement("div", "pilloway-checkout-right");
    var shipping = makeElement("section", "pilloway-checkout-shipping");
    shipping.setAttribute("aria-live", "polite");

    var shippingTable = makeElement("table", "pilloway-checkout-shipping-table");
    var shippingBody = document.createElement("tbody");
    shippingTable.appendChild(shippingBody);
    shipping.appendChild(shippingTable);

    var payment = makeElement("section", "pilloway-checkout-payment");
    var paymentTitle = makeElement("h3", "pilloway-checkout-payment-title");
    var paymentBody = makeElement("div", "pilloway-checkout-payment-body");
    payment.appendChild(paymentTitle);
    payment.appendChild(paymentBody);

    left.appendChild(shipping);
    left.appendChild(payment);
    layout.appendChild(left);
    layout.appendChild(right);
    form.insertBefore(layout, form.firstChild);

    return layout;
  }

  function arrangeCheckout() {
    var form = document.querySelector("form.checkout");
    if (!form) return;

    var layout = createLayout(form);
    var left = layout.querySelector(".pilloway-checkout-left");
    var right = layout.querySelector(".pilloway-checkout-right");
    var shipping = layout.querySelector(".pilloway-checkout-shipping");
    var shippingBody = layout.querySelector(".pilloway-checkout-shipping-table tbody");
    var paymentSection = layout.querySelector(".pilloway-checkout-payment");
    var paymentBody = layout.querySelector(".pilloway-checkout-payment-body");

    var customer = form.querySelector("#customer_details");
    var title = form.querySelector("#order_review_heading");
    var review = form.querySelector("#order_review");
    var notes = form.querySelector(".woocommerce-additional-fields");

    ensureChildOrder(left, [customer, shipping, paymentSection]);
    ensureChildOrder(right, [title, review, notes]);

    if (review && shippingBody) {
      var updatedRows = review.querySelectorAll("tr.woocommerce-shipping-totals.shipping");
      if (updatedRows.length) {
        while (shippingBody.firstChild) {
          shippingBody.removeChild(shippingBody.firstChild);
        }
        updatedRows.forEach(function (row) {
          shippingBody.appendChild(row);
        });
      }

      review.querySelectorAll("#nova-post-widget-root").forEach(function (widget) {
        moveTo(widget, shipping);
      });
    }

    var payment = form.querySelector("#payment");
    if (payment && paymentBody) {
      var paymentTitle = layout.querySelector(".pilloway-checkout-payment-title");
      var methods = payment.querySelector(".payment_methods");
      var label = methods ? methods.getAttribute("aria-label") : "";
      if (label) {
        if (paymentTitle.textContent !== label) paymentTitle.textContent = label;
        paymentTitle.hidden = false;
      } else {
        paymentTitle.hidden = true;
      }
      moveTo(payment, paymentBody);
    }
  }

  function queueArrangeCheckout() {
    if (arrangeQueued) return;
    arrangeQueued = true;
    window.requestAnimationFrame(function () {
      arrangeQueued = false;
      arrangeCheckout();
      updateDeliveryFields();
      queueInitialNovaStateRefresh();
    });
  }

  $(function () {
    arrangeCheckout();
    updateDeliveryFields();

    $(document.body).on(
      "change",
      'input[name^="shipping_method"], #billing_first_name, #billing_last_name, #billing_country',
      updateDeliveryFields
    );
    $(document.body).on("click", ".woocommerce-shipping-methods li", function (event) {
      if ($(event.target).closest("input, label, a, button, select, textarea").length) return;
      var radio = this.querySelector('input[type="radio"][name^="shipping_method"]');
      if (!radio || radio.checked || radio.disabled) return;
      radio.click();
    });
    $(document.body).on("click", ".wc_payment_methods li", function (event) {
      if ($(event.target).closest("input, label, a, button, select, textarea").length) return;
      var radio = this.querySelector('input[type="radio"][name="payment_method"]');
      if (!radio || radio.checked || radio.disabled) return;
      radio.click();
    });
    $(document.body).on("updated_checkout", function () {
      initialCheckoutUpdated = true;
      queueArrangeCheckout();
      queueInitialNovaStateRefresh();
    });

    var form = document.querySelector("form.checkout");
    if (form && window.MutationObserver) {
      var observer = new MutationObserver(queueArrangeCheckout);
      observer.observe(form, { childList: true, subtree: true });
    }
  });
})(jQuery);
