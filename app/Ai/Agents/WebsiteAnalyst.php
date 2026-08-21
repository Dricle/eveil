<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Stringable;

/**
 * Reads a product's own website and writes the portrait the Sales agent sells
 * from, because a salesperson has to know the product.
 *
 * This is the product knowledge base, not an SEO audit: we are
 * deliberately not building a site-audit product on the side.
 */
class WebsiteAnalyst extends EveilAgent implements HasStructuredOutput
{
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You are briefing a salesperson who will cold-email prospects about this product
        tomorrow morning, and who has never seen it. They need to sound like they
        understand it.

        You are given the text of several pages from one company's website. Work only
        from that text. Where the pages do not say something, say so plainly rather than
        inventing it. A confident guess that turns out wrong costs the salesperson their
        credibility on the first reply.

        Write every field in the language the website itself uses.

        Be concrete. "A platform that helps teams collaborate" is useless; name what the
        product actually does, for whom, and what it replaces. Prefer the words the
        company uses about itself over your own paraphrase.
        PROMPT.$this->projectInstructions();
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'what_it_does' => $schema->string()
                ->description('Two or three sentences: what the product is and what problem it removes.')
                ->required(),

            'who_it_is_for' => $schema->string()
                ->description('The kind of company and the kind of person who buys it.')
                ->required(),

            'value_proposition' => $schema->string()
                ->description('The single strongest reason someone switches to it.')
                ->required(),

            'positioning' => $schema->string()
                ->description('How it frames itself against the alternatives, including doing nothing.')
                ->required(),

            'key_features' => $schema->array()
                ->items($schema->string())
                ->description('Concrete capabilities named on the site, not marketing adjectives.')
                ->required(),

            'pricing_model' => $schema->string()
                ->description('How it charges, as stated. Say "not stated on the site" when it is not.')
                ->required(),

            'competitors' => $schema->array()
                ->items($schema->string())
                ->description('Competitors the site names. Empty when it names none, and do not guess.')
                ->required(),

            'proof_points' => $schema->array()
                ->items($schema->string())
                ->description('Customer names, figures, certifications or case studies the site claims.')
                ->required(),

            'language' => $schema->string()
                ->description('Two-letter code of the language the site is written in.')
                ->required(),

            'confidence' => $schema->integer()->min(0)->max(100)
                ->description('How well the pages actually supported this portrait. Low when the site was thin.')
                ->required(),

            'gaps' => $schema->array()
                ->items($schema->object([
                    'key' => $schema->string()
                        ->description(
                            'Stable identifier for this question, English snake_case, whatever the site language: '
                            .'minimum_order_size, deployment_model, service_area. Asking the same question again on a '
                            .'later reading must reuse the same key, because the answer is stored under it.'
                        )
                        ->required(),
                    'question' => $schema->string()
                        ->description('The question itself, asked plainly, in the language of the site.')
                        ->required(),
                ]))
                ->description(
                    'At most three questions a salesperson would still need answered before writing a good email, '
                    .'and only ones whose answer would change who is approached or what the mail says. '
                    .'Every question costs the user a minute, so leave out what is merely interesting. '
                    .'Empty when the pages answered everything that matters.'
                )
                ->required(),
        ];
    }
}
