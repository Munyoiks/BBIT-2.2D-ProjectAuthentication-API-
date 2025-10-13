(function() {
  // Initialize EmailJS
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
    email: email,        // Matches {{email}} in EmailJS template
    passcode: code,      // Matches {{passcode}} in template
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

// ✅ Send password reset email (for forgot password)
function sendResetEmail(email, link) {
  return emailjs.send(EMAILJS_CONFIG.SERVICE_ID, EMAILJS_CONFIG.TEMPLATE_RESET, {
    email: email,   // Matches {{email}} in EmailJS template
    link: link,     // Matches {{link}} in EmailJS template
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

// Automatically send reset email after token generation
function triggerPasswordReset(email, token) {
  const resetLink = `http://localhost/BBIT-2.2D-ProjectAuthentication-API-/reset_password.php?token=${token}&email=${encodeURIComponent(email)}`;
  
  sendResetEmail(email, resetLink)
    .then(() => {
      console.log("Password reset process started for:", email);
    })
    .catch((error) => {
      console.error("Failed to start password reset process:", error);
    });
}
