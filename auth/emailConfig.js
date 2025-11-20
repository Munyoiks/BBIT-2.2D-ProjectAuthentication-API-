// auth/emailConfig.js
(function () {
  console.log("Initializing EmailJS with 2 accounts (dynamic init)");

  // Account 1: OTP + Password Reset
  const ACC1 = {
    publicKey: "6vGjeTY7orp5_3YV3",
    serviceID: "service_6f9lgzi",
    otp: "template_gl8cj1l",
    reset: "template_sxlk0ia"
  };

  // Account 2: Invitations
  const ACC2 = {
    publicKey: "i7d19XEef_338EFix",
    serviceID: "service_7minwde",
    invite: "template_bn0j9jc"
  };

  // Track current initialized key
  let currentKey = null;

  // Switch EmailJS account
  function init(key) {
    if (currentKey !== key) {
      emailjs.init(key);
      currentKey = key;
      console.log("EmailJS initialized with key:", key);
    }
  }

  // OTP (Account 1)
  window.sendVerificationEmail = function (email, otp, name = "User") {
    init(ACC1.publicKey);
    return emailjs.send(ACC1.serviceID, ACC1.otp, { email, name, otp });
  };

  // Password Reset (Account 1)
  window.sendPasswordResetEmail = function (email, full_name, reset_link) {
    init(ACC1.publicKey);
    return emailjs.send(ACC1.serviceID, ACC1.reset, { email, full_name, reset_link });
  };

  // INVITATION (Account 2)
  window.sendInvitationEmail = function (email, data) {
    return new Promise((resolve, reject) => {
      init(ACC2.publicKey);
      emailjs.send(ACC2.serviceID, ACC2.invite, {
        email: email,
        full_name: data.full_name || 'New Occupant',
        invitation_link: data.invitation_link,
        primary_tenant: data.primary_tenant || 'Monrine Admin',
        unit_number: data.unit_number,
        role: data.role || 'Family Member',
        expires_at: data.expires_at
      })
      .then(resolve)
      .catch(err => {
        console.error("Invitation failed:", err);
        reject(err);
      });
    });
  };

  // Utilities
  window.formatRole = function(role) {
    const map = {
      'spouse': 'Spouse/Partner',
      'family': 'Family Member',
      'roommate': 'Roommate',
      'primary': 'Primary Tenant'
    };
    return map[role] || role.charAt(0).toUpperCase() + role.slice(1);
  };

  window.getExpirationDate = function(days = 7) {
    const d = new Date();
    d.setDate(d.getDate() + days);
    return d.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
  };

  console.log("EmailJS 2-account system ready (dynamic init)");
})();
