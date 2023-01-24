<?php

namespace SilverStripe\SMIME\Tests\Control;

use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use SilverStripe\Control\Email\Email;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\SMIME\Control\SMIMEMailer;
use Swift_ByteStream_FileByteStream;

class SMIMEMailerTest extends SapphireTest
{

    protected $usesDatabase = false; // phpcs:ignore

    public function testEncryptionOnly()
    {
        $recipientEncryptionCrt = $this->getAsset('/assets/smime_test_recipient.crt');
        $email = $this->buildEmail('Email with encryption', 'This is a confidential email.');
        $partialMockMailer = $this->createPartialMockedMIMEMailer([$recipientEncryptionCrt]);

        $partialMockMailer->expects($this->once())->method('sendSwift')->with(
            self::callback(function ($message) {
                $encryptedMessage = $message->toString();
                self::assertStringContainsString('Content-Type: application/x-pkcs7-mime;', $encryptedMessage);
                self::assertStringNotContainsString('This is a confidential email.', $encryptedMessage);

                return true;
            })
        );

        $partialMockMailer->send($email);
    }

    public function testSuccessfulDecryption()
    {
        $email = $this->buildEmail('Email with encryption', '<h1>This is a confidential email</h1>');
        $partialMockMailer = $this->createPartialMockedMIMEMailer([
            $this->getAsset('/assets/smime_test_recipient.crt')
        ]);

        $partialMockMailer->expects($this->once())->method('sendSwift')->with(
            self::callback(function ($message) {

                // Output email message to file
                $file = @tempnam(__DIR__ . '/tmp', 'smimetest');
                $bs = new Swift_ByteStream_FileByteStream($file, true);
                $message->toByteStream($bs);

                // Use openssl to extract private key, using password
                $privateKey = openssl_pkey_get_private(
                    file_get_contents($this->getAsset('/assets/smime_test_recipient.key')),
                    'Test123!'
                );

                // Decrypt email message
                $decryptedFile = @tempnam(__DIR__ . '/tmp', 'decrypted');
                openssl_cms_decrypt(
                    $file,
                    $decryptedFile,
                    file_get_contents($this->getAsset('/assets/smime_test_recipient.crt')),
                    $privateKey
                );

                // Check that decrypted file contains expected string
                self::assertStringContainsString(
                    '<h1>This is a confidential email</h1>',
                    file_get_contents($decryptedFile)
                );

                return true;
            })
        );

        $partialMockMailer->send($email);
    }

    public function testSigningOnly()
    {
        $recipientSigningCrt = $this->getAsset('/assets/smime_test_sender.crt');
        $recipientSigningKey = $this->getAsset('/assets/smime_test_sender.key');
        $email = $this->buildEmail(
            'Email smime signed by sender',
            '<h1>This is not a confidential email.</h1>'
        );

        $partialMockMailer = $this->createPartialMockedMIMEMailer([
            null,
            $recipientSigningCrt,
            $recipientSigningKey,
            'Test123!'
        ]);

        $partialMockMailer->expects($this->once())->method('sendSwift')->with(
            self::callback(function ($message) {
                // Convert the message to a string. This has signing applied to it
                $signedMessage = $message->toString();

                self::assertStringContainsString(
                    'Content-Type: application/x-pkcs7-signature; name="smime.p7s"',
                    $signedMessage
                );

                self::assertStringContainsString('This is an S/MIME signed message', $signedMessage);
                self::assertStringContainsString('<h1>This is not a confidential email.</h1>', $signedMessage);

                return true;
            })
        );

        $partialMockMailer->send($email);
    }

    public function testSigningErrorWithIncorrectSigningPassword()
    {
        $recipientSigningCrt = $this->getAsset('/assets/smime_test_sender.crt');
        $recipientSigningKey = $this->getAsset('/assets/smime_test_sender.key');
        $email = $this->buildEmail(
            'Email smime signed by sender',
            '<h1>This is not a confidential email.</h1>'
        );

        $partialMockMailer = $this->createPartialMockedMIMEMailer([
            null,
            $recipientSigningCrt,
            $recipientSigningKey,
            'IncorrectPassword!'
        ]);

        $partialMockMailer->expects($this->once())->method('sendSwift')->with(
            self::callback(function ($message) {
                $exception = null;
                try {
                    // Calling toString encrypts and signs.
                    // Here we expect an exception because of incorrect password
                    $message->toString();
                } catch (Exception $e) {
                    $exception = $e;
                } finally {
                    self::assertEquals(
                        'openssl_pkcs7_sign(): Error getting private key',
                        $exception->getMessage()
                    );
                }

                return true;
            })
        );

        $partialMockMailer->send($email);
    }

    public function testSigningAndEncryption()
    {
        $recipientEncryptionCrt = $this->getAsset('/assets/smime_test_recipient.crt');
        $recipientSigningCrt = $this->getAsset('/assets/smime_test_sender.crt');
        $recipientSigningKey = $this->getAsset('/assets/smime_test_sender.key');
        $email = $this->buildEmail(
            'Email encrypted and smime signed by sender',
            '<h1>This is a confidential email.</h1>'
        );

        $partialMockMailer = $this->createPartialMockedMIMEMailer([
            $recipientEncryptionCrt,
            $recipientSigningCrt,
            $recipientSigningKey,
            'Test123!'
        ]);

        $partialMockMailer->expects($this->once())->method('sendSwift')->with(
            self::callback(function ($message) {
                self::assertStringNotContainsString('This is a confidential email.', $message->toString());

                // Note, messages are signed and then encrypted, so the signing particulars are not seen unencrypted
                self::assertStringNotContainsString(
                    'Content-Type: application/x-pkcs7-signature; name="smime.p7s"',
                    $message
                );

                self::assertStringNotContainsString('This is an S/MIME signed message', $message);

                return true;
            })
        );

        $partialMockMailer->send($email);
    }

    public static function tearDownAfterClass(): void
    {
        // Disable as we don't have any db to reset
    }

    /**
     * Returns an SMIMEMailer instance with the sendSwift method mocked.
     * This allows us to perform assertions on the signed/encrypted message
     * that gets sent to the swift mailer.
     *
     * @param $args
     * @return MockObject|SMIMEMailer
     */
    private function createPartialMockedMIMEMailer($args)
    {
        return $this->getMockBuilder(SMIMEMailer::class)
            ->setConstructorArgs($args)
            ->setMethods(['sendSwift'])
            ->getMock();
    }

    /**
     * Helper function to build an email for test purposes.
     *
     * @param string|null $subject
     * @param string|null $body
     * @param string|null $recipient
     * @return Email
     */
    private function buildEmail(
        ?string $subject = '',
        ?string $body = '',
        ?string $recipient = 'recipient@example.com'
    ): Email {
        $email = new Email();
        $email->setSubject($subject);
        $email->setBody($body);
        $email->setSender('sender@example.com');
        $email->addTo($recipient);

        return $email;
    }

    /**
     * Helper function to get the full file path to an asset.
     *
     * @param string $filename
     * @return string
     */
    private function getAsset(string $filename): string
    {
        return sprintf('%s%s', __DIR__, $filename);
    }
}
