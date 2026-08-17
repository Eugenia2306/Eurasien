/**
 * Eurasien Gesellschaft: WordPress / Paid Memberships Pro URL map.
 * Paths stay root-relative (/app/...) so the same file works on staging and production.
 * Staging: https://eurasia.uwzghana.com/app/
 * Production: https://www.eurasien-gesellschaft.org/app/
 * Levels: 1 = Leserzugang (5 EUR/mo), 2 = Vereinsmitgliedschaft (120 EUR/yr)
 */
(function (w) {
  "use strict";
  var BASE = "/app";
  w.EG_APP = {
    enabled: true,
    base: BASE,
    login: BASE + "/login/?redirect_to=" + encodeURIComponent(BASE + "/membership-account/"),
    register: BASE + "/membership-checkout/?level=1",
    lostPassword: BASE + "/login/?action=reset_pass",
    authStatus: BASE + "/eg-auth-status.php",
    memberHandoff: BASE + "/eg-member-handoff.php",
    eventHandoff: BASE + "/eg-event-handoff.php",
    eventSuccess: BASE + "/eg-event-success.php",
    eventsFeed: BASE + "/eg-events.json.php",
    logout: BASE + "/wp-login.php?action=logout&redirect_to=" + encodeURIComponent("/"),
    account: BASE + "/membership-account/",
    /* Join flow: brochure registration form (not PMPro levels table). */
    membership: "/mitgliedschaft.html#membership-registration",
    checkoutReader: BASE + "/membership-checkout/?level=1",
    checkoutVerein: BASE + "/membership-checkout/?level=2",
    /* Event tickets: brochure modal posts to eventHandoff → Stripe Checkout. */
    checkoutEvent: BASE + "/eg-event-handoff.php",
    shop: BASE + "/shop/",
    cart: BASE + "/cart/",
    checkout: BASE + "/checkout/",
    books: BASE + "/shop/",
    events: BASE + "/events/",
    sampleBook: BASE + "/product/eurasien-gesellschaft-leseprobe/",
    gated: {
      positionen: BASE + "/mitglieder/positionen/",
      dossiers: BASE + "/mitglieder/dossiers/",
      studien: BASE + "/mitglieder/studien/"
    },
    levelIds: {
      reader: 1,
      verein: 2
    },
    productIds: {
      reader: 1,
      verein: 2,
      eventTicket: null
    },
    setLevelIds: function (ids) {
      if (!ids) return;
      if (ids.reader != null) {
        this.levelIds.reader = ids.reader;
        this.productIds.reader = ids.reader;
        this.checkoutReader =
          this.base + "/membership-checkout/?level=" + ids.reader;
      }
      if (ids.verein != null) {
        this.levelIds.verein = ids.verein;
        this.productIds.verein = ids.verein;
        this.checkoutVerein =
          this.base + "/membership-checkout/?level=" + ids.verein;
      }
    },
    setProductIds: function (ids) {
      this.setLevelIds(ids);
      if (ids && ids.eventTicket != null) {
        this.productIds.eventTicket = ids.eventTicket;
        this.checkoutEvent = String(ids.eventTicket);
      }
    }
  };
})(window);
