<?php

declare(strict_types=1);

namespace App\Service;

use Survos\BabelBundle\Event\TranslateStringEvent;
use Survos\BabelBundle\Runtime\BabelRuntime;
use Survos\CoreBundle\Service\SurvosUtils;
use Survos\JsonlBundle\IO\JsonlReader;
use Survos\JsonlBundle\IO\JsonlReaderInterface;
use Survos\JsonlBundle\IO\JsonlWriter;
use Survos\LibreTranslateBundle\Service\LibreTranslateService;
use Survos\TranslatorBundle\Model\TranslationRequest;
use Survos\TranslatorBundle\Service\TranslatorManager;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Psr\Log\LoggerInterface;

class TranslationService
{

    public function __construct(
        private TranslatorManager $translatorManager,
        private LoggerInterface $logger,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
//        private JsonlReaderInterface $jsonlReader,
    )
    {
    }


    #[AsEventListener(TranslateStringEvent::class)]
    public function onTranslateStringEvent(TranslateStringEvent $event): void
    {
        static $dictionaries = [];
        $dictionaries[$event->targetLocale] ??= [];
        $dict = &$dictionaries[$event->targetLocale];
        // the English is already translated for us.
        if ($event->targetLocale === 'en') {
            // this happens at the babel level, so we just translate the strings.
            // we are not updating the individual entities, e.g. with a doctrine iterator loop.
            if (empty($dict)) {
                $dict = array_replace(
                    json_decode(file_get_contents($this->projectDir.'/data/amst_dictionary.json'), true, flags: JSON_THROW_ON_ERROR),
                    json_decode(file_get_contents($this->projectDir.'/resources/translation-overrides.en.json'), true, flags: JSON_THROW_ON_ERROR),
                );
            }
            $src = $event->original;

            if (!array_key_exists($src, $dict)) {
                $this->logger->warning('No supplied English translation; keeping the Dutch source.', ['source' => $src]);
                return;
            }
            $event->translated = $dict[$src];
            return;
        }
        $fn = sprintf("%s/data/amst.%s.jsonl", $this->projectDir, $event->targetLocale);
        if (empty($dict)) {
            if (file_exists($fn)) {
                $reader = new JsonlReader($fn);
                foreach ($reader as $item) {
                    $dict[$item['src']] = $item['translated'];
                }
            }
        }
        if (array_key_exists($event->original, $dict)) {
            $event->translated = $dict[$event->original];
            return;
        }
        $translator = $this->translatorManager->by('libre');
//        $translator = $this->translatorManager->by('deepl');
        $writer = JsonlWriter::open($fn);

        $translated = $translator?->translate(
            new TranslationRequest($event->original, $event->sourceLocale, $event->targetLocale),
        );
        $obj = ['src' => $event->original, 'translated' => $translated->translatedText];
        $writer->write($obj);
        $dict[$event->original] = $translated->translatedText;
        $writer->close();
        $event->translated = $translated->translatedText;
    }


}
