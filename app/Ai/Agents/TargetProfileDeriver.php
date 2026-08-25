<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Stringable;

/**
 * Turns the product portrait into the target profiles the search runs on
 * This is the "zero configuration targeting" the whole product rests on.
 *
 * As many profiles as the product actually serves: flattening two markets into
 * one average profile targets nobody. But a segment nobody can be found for is
 * worse than no segment, so each one has to be searchable.
 */
class TargetProfileDeriver extends EveilAgent implements HasStructuredOutput
{
    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You are given the portrait of a product. Define the target profiles worth
        prospecting for it.

        A profile is only useful if someone could go and FIND those companies tomorrow.
        "Businesses that want to grow" is not a profile. These are, whatever the market:

        - "B2B software companies of 20 to 50 people running their own outbound, whose
          head of sales is named on the site"
        - "Independent physiotherapy practices with 2 to 5 practitioners, taking bookings
          by phone only"
        - "Metal fabrication subcontractors, 10 to 50 employees, exporting outside their
          home country"

        Each can be searched for on a map, in a directory, or on a search engine. That is
        the only test that matters.

        Derive as many profiles as the product genuinely serves, and no more. Most
        products have one to three. Splitting one market into artificial slices wastes
        the operator's money; merging two real markets into one average profile targets
        nobody. If two segments buy for the same reason and are found the same way, they
        are one profile.

        Then look past the buyer. Some markets are made of businesses that publish a
        phone number and no address at all. The profile is right and nobody in it can
        be written to. For those, and whenever one company already speaks to hundreds of
        the buyers, add a PARTNER profile: not who buys, but who already reaches the
        buyer. Ask three questions in this order:

        - Who is LEGALLY IMPOSED on them? A required certifier, an approved installer,
          a regulated register, a mandatory inspection body. Few of them, enumerable,
          with a captive clientele, which is the strongest partner profile there is.
        - Who invoices them every month? The accountant, the software vendor, the
          supplier, the franchisor.
        - Who physically visits them? The wholesaler's reps, the maintenance rounds,
          the delivery network.

        A partner profile is worth adding only when the partner is more reachable than
        the buyer, or when one of them carries many buyers. Say which through its
        access angle. Do not add one for the sake of symmetry, and never propose a
        partner you cannot say a concrete number about.

        Order customer profiles before partner profiles unless the buyer is genuinely
        unreachable.

        Order them by how worthwhile they are to prospect: how well the product fits,
        how reachable the buyer is, and how likely they are to buy, not by size alone.

        Ground every profile in the portrait. Where the portrait names customers,
        sectors, geographies or competitors, use them. Do not invent a market the
        product shows no sign of serving.

        Confidence asks a narrower question than it looks: would someone who knows this
        market immediately recognise the profile as correct and searchable, not whether
        the product's own site spells the buyer out in those words. Most sites describe
        what they do, not who exactly buys it, so reason from the product, its price,
        the examples it gives and ordinary market logic, the way you would advising a
        founder over coffee. A profile you would tell that founder to start searching for
        today deserves 70 or above. Reserve anything below 50 for a real guess: a market
        the portrait only hints at, or one you are inventing more than reading.

        Write the profiles in the language of the product's own market, not in English,
        unless the market is English-speaking.
        PROMPT.$this->projectInstructions();
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'profiles' => $schema->array()->items($schema->object([
                'name' => $schema->string()
                    ->description('Short handle a human would recognise, naming the segment and its market.')
                    ->required(),

                'type' => $schema->string()->enum(['customer', 'partner'])
                    ->description('`customer` buys the product. `partner` already reaches whoever buys it.')
                    ->required(),

                'access_angle' => $schema->string()
                    ->description('Partner profiles only: how this partner touches the buyer, how often, and roughly how many buyers one of them carries. Empty string for a customer profile.')
                    ->required(),

                'partnership_angle' => $schema->string()
                    ->description('Partner profiles only: why the deal is worth it to THEM. This becomes the opening line of the email, and "buy this" is never the answer. Empty string for a customer profile.')
                    ->required(),

                'rationale' => $schema->string()
                    ->description('Why this segment buys this product, in one or two sentences.')
                    ->required(),

                'sectors' => $schema->array()->items($schema->string())
                    ->description('Concrete lines of business, as someone searching a directory would name them.')
                    ->required(),

                'company_size' => $schema->string()
                    ->description('Headcount, number of locations or revenue band, whichever is observable from outside.')
                    ->required(),

                'geography' => $schema->array()->items($schema->string())
                    ->description('Countries, regions or cities. Be as narrow as the product warrants.')
                    ->required(),

                'job_titles' => $schema->array()->items($schema->string())
                    ->description('Who signs. For a small business this is usually the owner.')
                    ->required(),

                'technologies' => $schema->array()->items($schema->string())
                    ->description('Tools or platforms these companies visibly use, when that is a usable filter. Empty otherwise.')
                    ->required(),

                'trigger_signals' => $schema->array()->items($schema->string())
                    ->description('Observable events that mean now is the moment: hiring, opening, a competitor switch, a bad review wave.')
                    ->required(),

                'search_queries' => $schema->array()->items($schema->string())
                    ->description('Three to six queries, in the market language, that would actually surface these companies on a search engine or map.')
                    ->required(),

                'estimated_market_size' => $schema->string()
                    ->description('Rough count of companies matching, with the reasoning. Say so when you cannot tell.')
                    ->required(),

                'confidence' => $schema->integer()->min(0)->max(100)
                    ->description('0-100. Would someone who knows this market recognise it as correct and searchable today? Not whether the site literally names the buyer.')
                    ->required(),
            ]))->required(),
        ];
    }
}
