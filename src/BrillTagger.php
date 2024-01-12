<?php declare(strict_types=1);
/**
 * Part Of Speech Tagging
 * Brill Tagger
 *
 * @category   BrillTagger
 * @author     Ekin H. Bayar <me@ekins.space>
 * @version    0.2.0
 */

namespace BrillTagger;

class BrillTagger
{
    private static string $lexicon = 'lexicon.dat';

    private static array|null $dictionary = null;


    const NUMERAL = <<<'REGEX'
/
^
\p{Sc}?
(?=[([].*[)\]]|[^()[\]]*$)
[([]?
(?:\d+|\d+[,.]\d+)
(?:[,.]\d*)*?
[)\]]?
(?:\p{Sc}|p)?
$
/uix
REGEX;

    const YEAR = "/^('\d{2}|\d{4})(?<nns>'?s)?$/uix";

    const PERCENTAGE = "/^\d*\s?%$/uix";


    private static function loadDictionary(): void
    {
        if (self::$dictionary === null) {
            self::$dictionary = unserialize(file_get_contents(__DIR__.'/'.self::$lexicon));
        }
    }

    /**
     * @param string $token
     *
     * @return bool
     */
    public function tokenExists(string $token): bool
    {
        if (empty(static::$dictionary)) {
            static::loadDictionary();
        }

        return isset(static::$dictionary[$token]);
    }

    /**
     * @param $text
     * @return array
     */
    public function tag($text): array
    {

        preg_match_all("/[\w\.'%@]+/", $text, $matches);

        $tags = [];
        $i    = 0;

        foreach ($matches[0] as $token) {
            # default to a common noun
            $tags[$i] = ['token' => $token, 'tag' => 'NN'];

            # remove trailing full stops
            if (str_ends_with(trim($token), '.')) {
                $token = preg_replace('/\.+$/', '', $token);
            }

            # get from dictionary if set
            if ($this->tokenExists($token)) {
                $tags[$i]['tag'] = static::$dictionary[$token][0];
            }

            $tags[$i]['tag'] = $this->transformNumerics($tags[$i]['tag'], $token);

            # Anything that ends 'ly' is an adverb
            if ($this->isAdverb($token)) {
                $tags[$i]['tag'] = 'RB';
            }

            if ($this->isNoun($tags[$i]['tag']) && !$this->isProperNoun($tags[$i]['tag'])) {
                $tags[$i]['tag'] = $this->transformNoun($tags[$i]['tag'], $token);
            }

            if ($i > 0) {
                $tags[$i]['tag'] = $this->transformBetweenNounAndVerb($tags, $i, $token);
            }

            $i++;
        }

        return $tags;
    }

    /**
     * @param string $tag
     *
     * @return bool
     */
    public function isNoun(string $tag): bool
    {
        return str_starts_with(trim($tag), 'N');
    }

    /**
     * @param string $tag
     *
     * @return bool
     */
    public function isProperNoun(string $tag): bool
    {
        return str_starts_with(trim($tag), 'NP');
    }

    /**
     * @param string $tag
     *
     * @return bool
     */
    public function isSingularNoun(string $tag): bool
    {
        return $tag === 'NN';
    }

    /**
     * @param string $tag
     * @param string $token
     *
     * @return bool
     */
    public function isPluralNoun(string $tag, string $token): bool
    {
        return ($this->isNoun($tag) && str_ends_with($token, 's'));
    }

    /**
     * @param string $tag
     *
     * @return bool
     */
    public function isVerb(string $tag): bool
    {
        return str_starts_with(trim($tag), 'VB');
    }

    /**
     * @param string $tag
     *
     * @return bool
     */
    public function isPronoun(string $tag): bool
    {
        return str_starts_with(trim($tag), 'P');
    }

    /**
     * @param string $token
     *
     * @return bool
     */
    public function isPastTenseVerb(string $token): bool
    {
        return in_array('VBN', static::$dictionary[$token], true);
    }

    /**
     * @param string $token
     *
     * @return bool
     */
    public function isPresentTenseVerb(string $token): bool
    {
        return in_array('VBZ', static::$dictionary[$token], true);
    }

    /** it him me us you 'em thee we'uns
     *
     * @param string $tag
     *
     * @return bool
     */
    public function isAccusativePronoun(string $tag): bool
    {
        return str_starts_with(trim($tag), 'PPO');
    }

    /** it he she thee
     *
     * @param string $tag
     *
     * @return bool
     */
    public function isThirdPersonPronoun(string $tag): bool
    {
        return str_starts_with(trim($tag), 'PPS');
    }

    /** they we I you ye thou you'uns
     *
     * @param string $tag
     *
     * @return bool
     */
    public function isSingularPersonalPronoun(string $tag): bool
    {
        return str_starts_with(trim($tag), 'PPSS');
    }

    /** itself himself myself yourself herself oneself ownself
     *
     * @param string $tag
     *
     * @return bool
     */
    public function isSingularReflexivePronoun(string $tag): bool
    {
        return str_starts_with(trim($tag), 'PPL');
    }

    /** themselves ourselves yourselves
     *
     * @param string $tag
     *
     * @return bool
     */
    public function isPluralReflexivePronoun(string $tag): bool
    {
        return str_starts_with(trim($tag), 'PPLS');
    }

    /** ours mine his her/hers their/theirs our its my your/yours out thy thine
     *
     * @param string $tag
     *
     * @return bool
     */
    public function isPossessivePronoun(string $tag): bool
    {
        return in_array($tag, ['PP$$', 'PP$'], true);
    }

    /**
     * @param string $token
     *
     * @return bool
     */
    public function isAdjective(string $token): bool
    {
        return (str_ends_with($token, 'al') || in_array('JJ', static::$dictionary[$token], true));
    }

    /**
     * @param string $token
     *
     * @return bool
     */
    public function isGerund(string $token): bool
    {
        return str_ends_with($token, 'ing');
    }

    /**
     * @param string $token
     *
     * @return bool
     */
    public function isPastParticiple(string $token): bool
    {
        return str_ends_with($token, 'ed');
    }

    /**
     * @param string $token
     *
     * @return bool
     */
    public function isAdverb(string $token): bool
    {
        return str_ends_with($token, 'ly');
    }

    /** Common noun to adj. if it ends with 'al',
     * to gerund if 'ing', to past tense if 'ed'
     *
     * @param string $tag
     * @param string $token
     *
     * @return string
     */
    public function transformNoun(string $tag, string $token): string
    {

        if ($this->isAdjective($token)) {
            $tag = 'JJ';
        } elseif ($this->isGerund($token)) {
            $tag = 'VBG';
        } elseif ($this->isPastParticiple($token)) {
            $tag = 'VBN';
        } elseif ($token === 'I') {
            $tag = 'PPSS';
        } elseif ($this->isPluralNoun($tag, $token)) {
            $tag = 'NNS';
        }

        # Convert noun to number if . appears
        if (str_contains($token, '.')) {
            $tag = 'CD';
        }

        return $tag;
    }

    /**
     * @param array  $tags
     * @param int    $i
     * @param string $token
     *
     * @return mixed
     */
    public function transformBetweenNounAndVerb(array $tags, int $i, string $token): mixed
    {
        # Noun to verb if the word before is 'would'
        if ($tags[$i - 1]['token'] === 'would' && $this->isSingularNoun($tags[$i]['tag'])) {
            $tags[$i]['tag'] = 'VB';
        }

        # If we get noun noun, and the 2nd can be a verb, convert to verb
        if ($this->tokenExists($token)
            && $this->isNoun($tags[$i]['tag'])
            && $this->isNoun($tags[$i - 1]['tag'])
        ) {
            if ($this->isPastTenseVerb($token)) {
                $tags[$i]['tag'] = 'VBN';
            } elseif ($this->isPresentTenseVerb($token)) {
                $tags[$i]['tag'] = 'VBZ';
            }
        }

        # Converts verbs after 'the' to nouns
        if ($tags[$i - 1]['tag'] === 'DT' && $this->isVerb($tags[$i]['tag'])) {
            $tags[$i]['tag'] = 'NN';
        }

        return $tags[$i]['tag'];
    }

    /**
     * @param string $tag
     * @param string $token
     *
     * @return string
     */
    public function transformNumerics(string $tag, string $token): string
    {
        # tag numerals, cardinals, money (NNS)
        if (preg_match(self::NUMERAL, $token)) {
            $tag = 'NNS';
        }

        # tag years
        if (preg_match(self::YEAR, $token, $matches)) {
            $tag = isset($matches['nns']) ? 'NNS' : 'CD';
        }

        # tag percentages
        if (preg_match(self::PERCENTAGE, $token)) {
            $tag = 'NN';
        }

        return $tag;
    }
}
