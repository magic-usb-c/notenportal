<?php

declare(strict_types=1);

namespace App\Services\Notifications;

/**
 * Inhalt einer Benachrichtigung, unabhängig vom Layout. Wird serialisiert (Queue, Versandprotokoll) –
 * nur Strings und Arrays, keine Models.
 *
 * - lines:    Absätze nach der Anrede
 * - facts:    Label => Wert (z. B. «Datum» => «Mo 14.09.2026, 08:00»)
 * - sections: [['title' => …, 'text' => mehrzeilig]] – Zeilen mit «- » werden Aufzählung
 * - table:    ['head' => [...], 'rows' => [[...], …]] für Zusammenfassungen
 * - items:    [['group' => …, 'title' => …, 'text' => …, 'url' => …]] verlinkte Liste (Tageszusammenfassung)
 * - digest*:  eine Zeile für die Tageszusammenfassung (Standard: Betreff)
 * - title:    Überschrift in der Mail (Standard: Betreff)
 */
final class MailContent
{
    /**
     * @param  list<string>  $lines
     * @param  array<string, string>  $facts
     * @param  list<array{title: string, text: string}>  $sections
     * @param  array{head: list<string>, rows: list<list<string>>}|null  $table
     * @param  list<string>  $outro
     * @param  list<array{group?: string, title: string, text?: string|null, url?: string|null}>  $items
     */
    public function __construct(
        public string $subject,
        public array $lines = [],
        public array $facts = [],
        public array $sections = [],
        public ?string $actionLabel = null,
        public ?string $actionUrl = null,
        public array $outro = [],
        public ?string $preheader = null,
        public ?array $table = null,
        public ?string $digestTitle = null,
        public ?string $digestText = null,
        public ?string $title = null,
        public array $items = [],
    ) {}

    public function toArray(): array
    {
        return get_object_vars($this);
    }

    public static function fromArray(array $daten): self
    {
        $namen = array_map(fn (\ReflectionParameter $p) => $p->getName(), (new \ReflectionMethod(self::class, '__construct'))->getParameters());

        return new self(...array_intersect_key($daten, array_flip($namen)));
    }
}
