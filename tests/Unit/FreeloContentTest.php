<?php

namespace Tests\Unit;

use App\Support\FreeloContent;
use PHPUnit\Framework\TestCase;

class FreeloContentTest extends TestCase
{
    public function test_mentions_become_plain_names(): void
    {
        $html = '<div>Ahoj <span data-freelo-mention="1" data-freelo-user-id="29558">@Michelle</span>, <span data-freelo-mention="1" data-freelo-user-id="1">@Neznámý</span></div>';

        $this->assertSame(
            '<div>Ahoj @Michelle Losekoot, @Neznámý</div>',
            FreeloContent::toHtml($html, [29558 => 'Michelle Losekoot']),
        );
    }

    public function test_file_links_point_to_the_imported_attachment(): void
    {
        $html = '<div><a data-filename="image.png" data-freelo-file=\'{"filename":"x-image.png","uuid":"46647033-d9ca-48c8-8b2b-95e5d249d879"}\' target="_blank">image.png</a></div>';

        $result = FreeloContent::toHtml($html, [], ['46647033-d9ca-48c8-8b2b-95e5d249d879' => 'https://crm.test/todo-comment-attachments/7']);

        $this->assertStringContainsString('href="https://crm.test/todo-comment-attachments/7"', $result);
        $this->assertStringNotContainsString('data-freelo-file', $result);
        $this->assertStringContainsString('>image.png</a>', $result);
    }

    public function test_a_file_that_was_not_imported_keeps_only_its_name(): void
    {
        $html = '<div>Viz <a data-freelo-file=\'{"uuid":"missing"}\'>návrh.pdf</a></div>';

        $this->assertSame('<div>Viz návrh.pdf</div>', FreeloContent::toHtml($html));
    }

    public function test_empty_content_is_null(): void
    {
        $this->assertNull(FreeloContent::toHtml('  '));
        $this->assertNull(FreeloContent::toHtml(null));
    }

    public function test_the_description_comment_is_split_off(): void
    {
        [$description, $rest] = FreeloContent::splitDescription([
            ['id' => 1, 'is_description' => false],
            ['id' => 2, 'is_description' => true],
            ['id' => 3, 'is_description' => false],
        ]);

        $this->assertSame(2, $description['id']);
        $this->assertSame([1, 3], array_column($rest, 'id'));
    }
}
