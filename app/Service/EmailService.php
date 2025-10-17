<?php

declare(strict_types=1);

namespace App\Service;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Context\ApplicationContext;
use Hyperf\Logger\LoggerFactory;

class EmailService
{
    #[Inject]
    protected ConfigInterface $config;

    public function sendAlert(string $appName, string $message, array $log): bool
    {
        $to = $this->config->get('alert.email_recipients', []);

        if (empty($to)) {
            $logger = ApplicationContext::getContainer()
                ->get(LoggerFactory::class)
                ->get('alert');
            $logger->warning('Email recipients not configured');
            return false;
        }

        try {
            $subject = "🚨 Alert: {$appName} - Slow Process Detected";
            $body = $this->buildEmailBody($message, $log);

            // TODO: Implement actual email sending
            $logger = ApplicationContext::getContainer()
                ->get(LoggerFactory::class)
                ->get('alert');
            $logger->info('Email sent (placeholder)', [
                'to' => $to,
                'subject' => $subject,
            ]);

            return true;
        } catch (\Exception $e) {
            $logger = ApplicationContext::getContainer()
                ->get(LoggerFactory::class)
                ->get('alert');
            $logger->error('Email send error: ' . $e->getMessage());
            return false;
        }
    }

    private function buildEmailBody(string $message, array $log): string
    {
        $plainMessage = strip_tags(str_replace(['*', '_', '`'], '', $message));

        return sprintf(
            "<html><body>" .
                "<h2>Alert Notification</h2>" .
                "<pre>%s</pre>" .
                "<hr>" .
                "<h3>Log Details</h3>" .
                "<ul>" .
                "<li><strong>Index:</strong> %s</li>" .
                "<li><strong>ID:</strong> %s</li>" .
                "<li><strong>Correlation ID:</strong> %s</li>" .
                "</ul>" .
                "</body></html>",
            htmlspecialchars($plainMessage),
            $log['index'] ?? 'N/A',
            $log['id'] ?? 'N/A',
            $log['correlation_id'] ?? 'N/A'
        );
    }
}
