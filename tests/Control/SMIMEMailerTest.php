<?php

namespace SilverStripe\SMIME\Tests\Control;

use PHPUnit\Framework\MockObject\MockObject;
use SilverStripe\Control\Email\Email;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\SMIME\Control\SMIMEMailer;

class SMIMEMailerTest extends SapphireTest
{

    protected $usesDatabase = false; // phpcs:ignore

    public function testEncryptionOnly()
    {
        $recipientEncryptionCrt = $this->getAsset('/assets/smime_test_recipient.crt');
        $email = $this->buildEmail('Email with encryption', 'This is a confidential email.');
        $partialMockMailer = $this->createPartialMockedMIMEMailer([$recipientEncryptionCrt]);

        $partialMockMailer->expects($this->once())->method('sendSwift')->with(
            self::callback(function($message) {
                $encryptedMessage = $message->toString();
                self::assertStringContainsString('Content-Type: application/x-pkcs7-mime;', $encryptedMessage);
                self::assertStringNotContainsString('This is a confidential email.', $encryptedMessage);

                return true;
            })
        );

        $partialMockMailer->send($email);

    }

    public function testSigningOnly()
    {
        $recipientSigningCrt = $this->getAsset('/assets/smime_test_sender.crt');
        $recipientSigningKey = $this->getAsset('/assets/smime_test_sender.key');
        $email = $this->buildEmail('Email smime signed by sender', 'This is not a confidential email.');

        $partialMockMailer = $this->createPartialMockedMIMEMailer([null, $recipientSigningCrt, $recipientSigningKey, 'Test123!']);

        $partialMockMailer->expects($this->once())->method('sendSwift')->with(
            self::callback(function($message) {
                // Convert the message to a string. This has signing applied to it
                $signedMessage = $message->toString();

                self::assertStringContainsString('Content-Type: application/x-pkcs7-signature; name="smime.p7s"', $signedMessage);
                self::assertStringContainsString('This is an S/MIME signed message', $signedMessage);
                self::assertStringContainsString('This is not a confidential email.', $signedMessage);

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
        $email = $this->buildEmail('Email encrypted and smime signed by sender', 'This is a confidential email.');

        $partialMockMailer = $this->createPartialMockedMIMEMailer([
            $recipientEncryptionCrt,
            $recipientSigningCrt,
            $recipientSigningKey,
            'Test123!'
        ]);

        $partialMockMailer->expects($this->once())->method('sendSwift')->with(
            self::callback(function($message) {
                self::assertStringNotContainsString('This is a confidential email.', $message->toString());
                // Note, messages are signed and then encrypted, so the signing particulars are not seen unencrypted
                self::assertStringNotContainsString('Content-Type: application/x-pkcs7-signature; name="smime.p7s"', $message);
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
     * @return Email
     */
    private function buildEmail(?string $subject = '', ?string $body = ''): Email
    {
        $email = new Email();
        $email->setSubject($subject);
        $email->setBody($body);
        $email->setSender('sender@example.com');
        $email->addTo('recipient@example.com');

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
