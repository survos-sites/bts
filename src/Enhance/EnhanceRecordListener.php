<?php

namespace App\Enhance;

use Sentry\HttpClient\HttpClient;
use Survos\ImportBundle\Event\ImportConvertFinishedEvent;
use Survos\JsonlBundle\Event\JsonlConvertStartedEvent;
use Survos\JsonlBundle\Event\JsonlRecordEvent;
use Survos\MediaBundle\Service\MediaRegistry;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use function Symfony\Component\String\u;
use Survos\ImportBundle\Event\ImportConvertRowEvent;
class EnhanceRecordListener
{
    private $seen = [];

    public function __construct(
        private SluggerInterface    $asciiSlugger,
        private HttpClientInterface $httpClient,
        private MediaRegistry $mediaRegistry
    )
    {
    }

    #[AsEventListener(event: ImportConvertFinishedEvent::class)]
    final public function finished(ImportConvertFinishedEvent $event): void
    {
        $this->mediaRegistry->flush();
    }
    #[AsEventListener(event: ImportConvertRowEvent::class)]
    final public function tweakRecord(ImportConvertRowEvent $event): void
    {
        $record = $event->row;
        $record = array_filter($record, static fn (mixed $value): bool => null !== $value && [] !== $value);
//        foreach ($event->tags as $tag)
        {
            switch ($event->dataset) {
                case 'amst':
                case 'amst_en':
                case 'amst_nl':
                    // bad: https://statics.belowthesurface.amsterdam/vondst/600/NZC1.00001MTL001(01).png
                    // YES: https://statics.belowthesurface.amsterdam/vondst/600/NZD1.00048FAU017-02(01).png
//                    https://statics.belowthesurface.amsterdam/vondst/600/NZC1.00001MTL001(01).png
//                    https://statics.belowthesurface.amsterdam/vondst/600/NZD1.00048FAU017-02(01).png
                    $code = $record['vondstnummer']; // with the .
                    if (!$record['website']) {
                        $event->row = null;
                        return;
                    }
                    // The source CSV includes finds that have no public detail page or image.
                    unset($record['citation_url'], $record['image']);
                    if ($record['website']) {
                        $record['citation_url'] = sprintf('https://belowthesurface.amsterdam/nl/vondst/%s', $code);
                        $image = $record['image'] = sprintf('https://statics.belowthesurface.amsterdam/vondst/600/%s(01).png',
                            $code);
                        $hash = hash('xxh3', $image);
                        if (!in_array($hash, $this->seen)) {
                            $this->mediaRegistry->ensureMedia($image, flush: false);
                            $this->seen[] = $hash;
                        }
//                        $response = $this->httpClient->request('GET', $image);
//                        if ($response->getStatusCode() <> 200) {
//                            dd($response, $image);
//                        } else {
//                            dump($image);
//                        }
//                        dd($response->getStatusCode());
//                        dd(array_keys($record));
                    }
                    foreach ($record as $var=>$value) {
                        if (in_array($var, ['trefwoorden','vlak_min', 'vlak'])) {
                            $record[$var] = explode(';', $value);
                        }
                    }
//                    dd($record['citation_url']);
                    $code = str_replace('.', '-', $code);
                    if (in_array($code, $this->seen)) {
                        $event->row = null;
                        dump($code . ' already seen', $event);
                        return;
                    }
                    $this->seen[] = $code;
                    $record['code'] = $code;
//                    $record['vondstnummer'] = $code;
//                    dump($record);
//                    dump('https://statics.belowthesurface.amsterdam/vondst/600/NZD1.00048FAU017-02(01).png');
//                    dd($record['image'], $record['citation_url']);
                    break;
            }
//            dd($record, $event->row);
            if ($event->index % 1000 == 0) {
                $this->mediaRegistry->flush();
            }

            $event->row = $record;
        }

    }
}
