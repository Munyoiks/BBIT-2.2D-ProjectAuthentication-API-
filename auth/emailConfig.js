(function() {
    emailjs.init({
        publicKey: 'RFl2-4eHenzarWon4'
    });
})();

function sendVerificationEmail(email, code) {
    return emailjs.send('service_e594fkz', 'template_wzft06q', {
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