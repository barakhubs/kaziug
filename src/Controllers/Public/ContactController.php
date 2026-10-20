<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Format;
use App\Core\Http\Request;
use App\Services\Contracts\SettingsReaderInterface;
use App\Services\Seo\StructuredData;
use App\Core\Http\Responder;
use App\Core\Http\Response;
use App\Core\Log\LoggerInterface;
use App\Domain\ContactMessage;
use App\Mail\MailException;
use App\Services\Contracts\ContactServiceInterface;
use App\Services\FormTimer;
use App\Validation\Validator;

/** The Contact page and the CV help page: a form that emails the site, plus WhatsApp. */
final class ContactController
{
    private const PAGES = [
        ContactMessage::TOPIC_GENERAL => ['template' => 'public/contact', 'path' => '/contact'],
        ContactMessage::TOPIC_CV => ['template' => 'public/cv_help', 'path' => '/cv-help'],
    ];

    public function __construct(
        private readonly ContactServiceInterface $contact,
        private readonly Validator $validator,
        private readonly FormTimer $timer,
        private readonly LoggerInterface $logger,
        private readonly Responder $responder,
        private readonly SettingsReaderInterface $settings,
        private readonly StructuredData $structuredData,
    ) {
    }

    public function showContact(Request $request): Response
    {
        return $this->page(ContactMessage::TOPIC_GENERAL, [], []);
    }

    public function sendContact(Request $request): Response
    {
        return $this->handle($request, ContactMessage::TOPIC_GENERAL);
    }

    public function showCvHelp(Request $request): Response
    {
        return $this->page(ContactMessage::TOPIC_CV, [], []);
    }

    public function sendCvHelp(Request $request): Response
    {
        return $this->handle($request, ContactMessage::TOPIC_CV);
    }

    private function handle(Request $request, string $topic): Response
    {
        $path = self::PAGES[$topic]['path'];
        $input = $this->validator->validate($request->all(), [
            'name' => 'required|max:100',
            'email' => 'required|email',
            'phone' => 'max:30',
            'message' => 'required|max:5000',
        ]);
        $errors = $input->errors;
        if (!isset($errors['phone']) && $input->data['phone'] !== null && preg_match('/^\+?[0-9 ()\-]{7,30}$/', (string) $input->data['phone']) !== 1) {
            $errors['phone'] = 'Enter a phone number, e.g. +256 772 123456.';
        }
        if (!isset($errors['message']) && mb_strlen((string) $input->data['message']) < 10) {
            $errors['message'] = 'Please tell us a little more (at least 10 characters).';
        }
        if ($errors !== []) {
            return $this->page($topic, $errors, $request->all(), 422);
        }

        // Bots get the normal thank-you so they learn nothing; nothing is sent.
        if ($request->string('website') !== '' || !$this->timer->isHuman($request->string(FormTimer::FIELD))) {
            $this->logger->info('Contact form rejected as automated', ['ip' => $request->ip(), 'topic' => $topic]);

            return $this->responder->redirectWith($request, $path, 'success', 'Thank you! Your message has been sent. We’ll get back to you soon.');
        }

        $message = new ContactMessage(
            $topic,
            (string) $input->data['name'],
            strtolower((string) $input->data['email']),
            $input->data['phone'] === null ? null : (string) $input->data['phone'],
            (string) $input->data['message'],
        );

        try {
            $wait = $this->contact->send($message, $request->ip());
        } catch (MailException $e) {
            $this->logger->error('Contact form email failed', ['error' => $e->getMessage(), 'topic' => $topic]);

            return $this->page($topic, ['form' => 'We couldn’t send your message right now. Please try again later, or reach us on WhatsApp.'], $request->all(), 503);
        }
        if ($wait > 0) {
            $minutes = (int) ceil($wait / 60);

            return $this->page($topic, ['form' => "You’ve sent several messages already. Please try again in about {$minutes} minute(s), or reach us on WhatsApp."], $request->all(), 429)
                ->setHeader('Retry-After', (string) $wait);
        }

        return $this->responder->redirectWith($request, $path, 'success', 'Thank you! Your message has been sent. We’ll get back to you soon.');
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, mixed> $values
     */
    private function page(string $topic, array $errors, array $values, int $status = 200): Response
    {
        $data = [
            'errors' => $errors,
            'values' => $values,
            'formAvailable' => $this->contact->isAvailable(),
            'formAction' => self::PAGES[$topic]['path'],
        ];
        if ($topic === ContactMessage::TOPIC_CV) {
            $site = $this->settings->site();
            $faq = $this->faq();
            $data['faq'] = $faq;
            $data['jsonLd'] = [$this->structuredData->cvService($site), $this->structuredData->faq($faq)];
        }

        return $this->responder->view(self::PAGES[$topic]['template'], $data, $status);
    }

    /** @return list<array{question: string, answer: string}> */
    private function faq(): array
    {
        $site = $this->settings->site();
        $replace = ['{price}' => Format::money($site->cvServicePrice), '{site}' => $site->siteName];

        return array_map(static fn (array $item): array => [
            'question' => strtr((string) $item['question'], $replace),
            'answer' => strtr((string) $item['answer'], $replace),
        ], require dirname(__DIR__, 3) . '/config/content/cv_help_faq.php');
    }
}
