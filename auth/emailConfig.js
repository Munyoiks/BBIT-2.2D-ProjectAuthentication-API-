(function() {
    emailjs.init({
        publicKey: 'T38uilUqfOVLAnbQE'
    });
})();

function sendVerificationEmail(email, code) {
    return emailjs.send('service_hit0nhj', 'template_lyjg5vx', {
        to_email: email,
        verification_code: code
    }).then(() => {
        alert('Verification code sent to ' + email);
        window.location.href = 'verify.php';
    }).catch((err) => {
        console.error('EmailJS error:', err);
        alert('Failed to send verification email. Try again.');
        window.location.href = 'verify.php';
    });
}