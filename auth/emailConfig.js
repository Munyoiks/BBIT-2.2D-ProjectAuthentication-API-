(function() {
  emailjs.init({ publicKey: "T38uilUqfOVLAnbQE" });
})();

const EMAILJS_CONFIG = {
  SERVICE_ID: "service_hit0nhj",
  TEMPLATE_VERIFY: "template_lyjg5vx", // OTP template
  TEMPLATE_RESET: "template_hq97rqj"   // Password reset
};

// ✅ Send verification (OTP) email
function sendVerificationEmail(email, code) {
  return emailjs.send(EMAILJS_CONFIG.SERVICE_ID, EMAILJS_CONFIG.TEMPLATE_VERIFY, {
    email: email,        // ✅ matches {{email}} in your EmailJS template
    passcode: code,      // ✅ ensure EmailJS template uses {{passcode}} variable
  })
  .then(() => {
    console.log("Verification code sent to", email);
    alert("Verification code sent to " + email);
    window.location.href = "verify.php";
  })
  .catch((err) => {
    console.error("EmailJS Verification Error:", err);
    alert("Failed to send verification email. Please try again.");
  });
}

// ✅ Send password reset email
function sendResetEmail(email, link) {
  return emailjs.send(EMAILJS_CONFIG.SERVICE_ID, EMAILJS_CONFIG.TEMPLATE_RESET, {
    email: email,   // ✅ must match {{email}} in EmailJS template
    link: link,     // ✅ must match {{link}} in EmailJS template
  })
  .then(() => {
    console.log("Reset email sent to", email);
    alert("Password reset link sent to " + email);
  })
  .catch((err) => {
    console.error("EmailJS Reset Error:", err);
    alert("Failed to send password reset email. Please try again later.");
  });
}
