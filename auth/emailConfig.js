(function () {
  // Initialize EmailJS
  emailjs.init({
    publicKey: "6vGjeTY7orp5_3YV3", // keep yours
  });

  console.log(" EmailJS initialized successfully");

  // Expose globally for OTP sending
  window.sendVerificationEmail = function (email, otp, name = "User") {
    const templateParams = {
      email,
      name,
      otp,
    };

    return emailjs.send("service_6f9lgzi", "template_gl8cj1l", templateParams);
  };

  // Expose globally for password reset
  window.sendPasswordResetEmail = function (email, full_name, reset_link) {
    const templateParams = {
      email,
      full_name,
      reset_link, // this must match your EmailJS variable name
    };

    return emailjs.send("service_6f9lgzi", "template_sxlk0ia", templateParams);
  };
})();
