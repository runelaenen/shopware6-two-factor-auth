<?php

declare(strict_types=1);

namespace Tinect\TwoFactorAuth\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Tinect\TwoFactorAuth\Service\EmailCodeService;

/**
 * Adds the table that marks customers who chose codes by e-mail, and the mail template those codes are sent with.
 */
class Migration1795564800EmailCode extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1795564800;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `rl_2fa_customer_email` (
                `customer_id` BINARY(16) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`customer_id`),
                CONSTRAINT `fk.rl_2fa_customer_email.customer_id` FOREIGN KEY (`customer_id`)
                    REFERENCES `customer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $this->createMailTemplate($connection);
    }

    private function createMailTemplate(Connection $connection): void
    {
        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $typeId = $connection->fetchOne(
            'SELECT `id` FROM `mail_template_type` WHERE `technical_name` = :name',
            ['name' => EmailCodeService::MAIL_TEMPLATE_TYPE]
        );

        if (!\is_string($typeId)) {
            $typeId = Uuid::randomBytes();
            $connection->insert('mail_template_type', [
                'id' => $typeId,
                'technical_name' => EmailCodeService::MAIL_TEMPLATE_TYPE,
                'available_entities' => json_encode(['customer' => 'customer', 'salesChannel' => 'sales_channel'], \JSON_THROW_ON_ERROR),
                'created_at' => $now,
            ]);
        }

        $templateId = $connection->fetchOne(
            'SELECT `id` FROM `mail_template` WHERE `mail_template_type_id` = :typeId AND `system_default` = 1',
            ['typeId' => $typeId]
        );

        if (!\is_string($templateId)) {
            $templateId = Uuid::randomBytes();
            $connection->insert('mail_template', [
                'id' => $templateId,
                'mail_template_type_id' => $typeId,
                'system_default' => 1,
                'created_at' => $now,
            ]);
        }

        $germanLanguageIds = $this->getLanguageIds($connection, true);
        // The system language always needs a translation, whatever its locale is.
        $otherLanguageIds = array_values(array_unique(array_diff(
            [...$this->getLanguageIds($connection, false), Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)],
            $germanLanguageIds
        )));

        foreach ([[$germanLanguageIds, $this->german()], [$otherLanguageIds, $this->english()]] as [$languageIds, $content]) {
            foreach ($languageIds as $languageId) {
                $connection->executeStatement(
                    'INSERT IGNORE INTO `mail_template_type_translation` (`mail_template_type_id`, `language_id`, `name`, `created_at`)
                    VALUES (:typeId, :languageId, :name, :now)',
                    ['typeId' => $typeId, 'languageId' => $languageId, 'name' => $content['name'], 'now' => $now]
                );

                $connection->executeStatement(
                    'INSERT IGNORE INTO `mail_template_translation`
                        (`mail_template_id`, `language_id`, `sender_name`, `subject`, `description`, `content_html`, `content_plain`, `created_at`)
                    VALUES (:templateId, :languageId, :senderName, :subject, :description, :html, :plain, :now)',
                    [
                        'templateId' => $templateId,
                        'languageId' => $languageId,
                        'senderName' => '{{ salesChannel.translated.name }}',
                        'subject' => $content['subject'],
                        'description' => $content['name'],
                        'html' => $content['html'],
                        'plain' => $content['plain'],
                        'now' => $now,
                    ]
                );
            }
        }
    }

    /**
     * @return list<string>
     */
    private function getLanguageIds(Connection $connection, bool $german): array
    {
        /** @var list<string> $ids */
        $ids = $connection->fetchFirstColumn(
            \sprintf(
                'SELECT `language`.`id` FROM `language`
                INNER JOIN `locale` ON `locale`.`id` = `language`.`locale_id`
                WHERE LOWER(`locale`.`code`) %s \'de-%%\'',
                $german ? 'LIKE' : 'NOT LIKE'
            )
        );

        return $ids;
    }

    /**
     * @return array{name: string, subject: string, html: string, plain: string}
     */
    private function english(): array
    {
        return [
            'name' => 'Two-factor authentication: login code',
            'subject' => 'Your login code for {{ salesChannel.translated.name }}',
            'html' => <<<'TWIG'
<div style="font-family:arial; font-size:12px;">
    <p>Hello {{ customer.firstName }} {{ customer.lastName }},</p>
    <p>your login code is:</p>
    <p style="font-size:24px; font-weight:bold; letter-spacing:4px;">{{ code }}</p>
    <p>The code is valid for {{ expiresInMinutes }} minutes.</p>
    <p>If you did not try to log in, change your password right away. Someone knows it.</p>
</div>
TWIG,
            'plain' => <<<'TWIG'
Hello {{ customer.firstName }} {{ customer.lastName }},

your login code is: {{ code }}

The code is valid for {{ expiresInMinutes }} minutes.

If you did not try to log in, change your password right away. Someone knows it.
TWIG,
        ];
    }

    /**
     * @return array{name: string, subject: string, html: string, plain: string}
     */
    private function german(): array
    {
        return [
            'name' => 'Zwei-Faktor-Authentifizierung: Anmeldecode',
            'subject' => 'Ihr Anmeldecode für {{ salesChannel.translated.name }}',
            'html' => <<<'TWIG'
<div style="font-family:arial; font-size:12px;">
    <p>Hallo {{ customer.firstName }} {{ customer.lastName }},</p>
    <p>Ihr Anmeldecode lautet:</p>
    <p style="font-size:24px; font-weight:bold; letter-spacing:4px;">{{ code }}</p>
    <p>Der Code ist {{ expiresInMinutes }} Minuten gültig.</p>
    <p>Falls Sie sich nicht anmelden wollten, ändern Sie bitte umgehend Ihr Passwort. Jemand anderes kennt es.</p>
</div>
TWIG,
            'plain' => <<<'TWIG'
Hallo {{ customer.firstName }} {{ customer.lastName }},

Ihr Anmeldecode lautet: {{ code }}

Der Code ist {{ expiresInMinutes }} Minuten gültig.

Falls Sie sich nicht anmelden wollten, ändern Sie bitte umgehend Ihr Passwort. Jemand anderes kennt es.
TWIG,
        ];
    }
}
