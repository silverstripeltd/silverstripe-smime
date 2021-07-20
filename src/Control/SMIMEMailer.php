<?php

namespace SilverStripe\SMIME\Control;

use SilverStripe\Control\Email\Email;
use SilverStripe\Control\Email\Mailer;
use SilverStripe\Control\Email\SwiftMailer;
use Swift_Mailer;
use Swift_Signers_SMimeSigner;

/**
 * Class SMIMEMailer
 *
 * Mailer that securely encrypts and signs emails
 */
class SMIMEMailer extends SwiftMailer
{
    /**
     * @var array
     */
    private static $dependencies = [
        'SwiftMailer' => '%$' . Swift_Mailer::class,
        'SMimeSigner' => '%$' . Swift_Signers_SMimeSigner::class,
    ];

    /**
     * @var string|null $signCertificate
     */
    protected $signCertificate;

    /**
     * @var array|null $signPrivateKey
     */
    protected $signPrivateKey;

    /**
     * @var array|null $encryptCerts
     */
    protected $encryptCerts;

    /**
     * @var array|null $options
     */
    protected $options;

    /**
     * @var array|null $default_options
     */
    private static $default_options = [];

    /**
     * SMIMEMailer constructor.
     *
     * @param array|null $encryptCert Path to encrypt certificate (recipient)
     * @param string|null $signCertificate Path to signing certificate (sender)
     * @param string|null $signPrivateKey Path to private key
     * @param string|null $signKeyPassphrase Private key passphrase
     * @param array $options
     *
     * @return void
     */
    public function __construct(
        ?array $encryptCert = null,
        ?string $signCertificate = null,
        ?string $signPrivateKey = null,
        ?string $signKeyPassphrase = null,
        array $options = []
    )
    {
        $this->setEncryptCerts($encryptCert);
        $this->setSigningCert($signCertificate);
        $this->setSigningPrivateKey(
            $signPrivateKey,
            $signKeyPassphrase
        );

        $this->setSwiftSignerOptions($options);
    }


    /**
     * Set options for Swift_Signers_SMimeSigner.
     *
     * Some options are always overridden if environment variables are present. This allows for ease of set up in
     * testing environments, providing assurance of settings.
     *
     * @param array $options Option set. {@see openssl_pkcs7_sign} for available flags
     *
     * @return $this
     */
    public function setSwiftSignerOptions(array $options = []): self
    {
        $options = $options ?: $this->config()->get('default_options');

        $this->options = $options;

        return $this;
    }

    /**
     * Sets the encryption certificate for this mailer.
     *
     * @param array|null $encryptCerts
     *
     * @return $this
     */
    public function setEncryptCerts(?array $encryptCerts = null): self
    {
        $this->encryptCerts = $encryptCerts;
        return $this;
    }

    /**
     * Sets the signing certificate for this mailer.
     *
     * @param string|null $signCertificate
     *
     * @return $this
     */
    public function setSigningCert(?string $signCertificate = null): self
    {
        $this->signCertificate = $signCertificate;
        return $this;
    }

    /**
     * Sets the signing certificate for this mailer.
     * Can be stored as array or string.
     *
     * @param string|null $signPrivateKey
     * @param string|null $signKeyPassphrase
     *
     * @return $this
     * @see Swift_Signers_SMimeSigner::setSignCertificate()
     *
     */
    public function setSigningPrivateKey(?string $signPrivateKey = null, ?string $signKeyPassphrase = null): self
    {
        // Set passphrase to blank string if it is null
        $passphrase = $signKeyPassphrase ?:  '';

        // Assign as array
        $this->signPrivateKey = [
            $signPrivateKey,
            $passphrase
        ];

        return $this;
    }

    /**
     * @param Email $message
     *
     * @return bool Whether the sending was "successful" or not
     * @see Mailer::send()
     */
    public function send($message): bool
    {
        // Get swift message from Email
        $swiftMessage = $message->getSwiftMessage();

        // Create our S/MIME signer
        $sMimeSigner = new Swift_Signers_SMimeSigner();

        // Add our certificate, key, and password.
        if ($this->signCertificate) {
            $sMimeSigner->setSignCertificate($this->signCertificate, $this->signPrivateKey);
        }

        // Add our encryption certificate (the matching certificate to our local private key)
        if ($this->encryptCerts) {
            $sMimeSigner->setEncryptCertificate($this->encryptCerts);
        }

        // Attach the signer to our message
        $swiftMessage->attachSigner($sMimeSigner);

        $result = $this->sendSwift($swiftMessage, $failedRecipients);

        $message->setFailedRecipients($failedRecipients);

        // The 0 number of successful recipients indicates failure
        return $result !== 0;
    }
}
