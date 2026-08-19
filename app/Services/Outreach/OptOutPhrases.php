<?php

namespace App\Services\Outreach;

/**
 * The safety net under the reply agent, not a replacement for it.
 *
 * Replying is the only opt-out channel this product offers, so compliance must
 * not depend on a provider being up, a quota being unspent, or a model reading
 * a sentence correctly. When one of these appears, the address is suppressed
 * whatever the agent later decides — and the agent still runs, because "STOP,
 * and by the way send it to my colleague instead" needs both.
 *
 * Only unambiguous phrasing belongs here. Everything requiring judgement — "not
 * a priority this year", "we already have a supplier" — is the agent's job; a
 * keyword list that tries to be clever is how a warm lead gets suppressed.
 *
 * Multilingual because the mail was written in the prospect's language: this
 * app writes to Wallonia and Flanders in one afternoon, so an English-only list
 * would fail exactly where it matters.
 */
class OptOutPhrases
{
    /**
     * Whole words and phrases, matched between word boundaries.
     *
     * `stop` is here rather than among the stems on purpose: as a stem it would
     * fire on "we stopped using that supplier", which is a sales conversation
     * and not an opt-out.
     *
     * @var array<int, string>
     */
    private const PHRASES = [
        'stop', 'unsubscribe',

        // Sentences that mean it without using the word.
        'remove me from', 'take me off', 'do not contact me', 'dont contact me',
        'no longer wish to receive', 'stop emailing me', 'stop contacting me',
        'ne plus me contacter', 'ne plus m ecrire', 'ne plus recevoir',
        'retirez moi', 'supprimez moi', 'arretez de m ecrire',
        'geen mail meer', 'niet meer contacteren', 'schrijf me uit',
        // Dutch separates the verb, so the stem below never sees it joined up.
        'uit te schrijven', 'me uitschrijven',
        'keine weiteren e mails', 'nicht mehr kontaktieren',
    ];

    /**
     * Word STARTS, because these are verbs and the whole point is not to keep a
     * conjugation table: `desinscri` covers "désinscription", "désinscrire",
     * "désinscrivez-moi" and "je me désinscris" in one entry.
     *
     * Only families where every ending means the same thing belong here.
     *
     * @var array<int, string>
     */
    private const STEMS = [
        'desinscri', 'desabonn', 'uitschrijv', 'afmeld', 'abmeld',
        'cancelar suscripcion', 'unsubscrib',
    ];

    public function found(string $body, string $subject = ''): bool
    {
        $text = $this->normalise($subject.' '.$body);

        foreach (self::PHRASES as $phrase) {
            // Both boundaries: "stop" must not fire inside "unstoppable".
            if (preg_match('/\b'.preg_quote($phrase, '/').'\b/', $text) === 1) {
                return true;
            }
        }

        foreach (self::STEMS as $stem) {
            // Leading boundary only, so any ending on the verb still matches.
            if (preg_match('/\b'.preg_quote($stem, '/').'/', $text) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Accents off, punctuation to spaces, one space between words: this is what
     * makes one phrase match "Désinscrivez-moi !", "desinscrivez moi" and
     * "DESINSCRIVEZ MOI" without three entries in the list.
     */
    private function normalise(string $text): string
    {
        $lower = mb_strtolower($text);
        $plain = @iconv('UTF-8', 'ASCII//TRANSLIT', $lower);

        if ($plain === false) {
            $plain = $lower;
        }

        return mb_trim((string) preg_replace(['/[^a-z0-9]+/', '/\s+/'], ' ', $plain));
    }
}
