<?php

namespace Govorun\Tests\Unit\Drivers\Concerns;

use Govorun\Tests\TestCase;
use Govorun\Drivers\Concerns\ConvertsHtmlToPlainText;

class ConvertsHtmlToPlainTextTest extends TestCase
{
    private object $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new class {
            use ConvertsHtmlToPlainText;
            public function run(string $html): string { return $this->htmlToPlainText($html); }
        };
    }

    public function test_link_becomes_text_with_url(): void
    {
        $this->assertSame(
            'click (https://example.com)',
            $this->subject->run('<a href="https://example.com">click</a>'),
        );
    }

    public function test_strips_tags_and_decodes_entities(): void
    {
        $this->assertSame('bold & italic', $this->subject->run('<b>bold</b> &amp; <i>italic</i>'));
    }
}