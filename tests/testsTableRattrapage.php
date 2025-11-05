<?php
use PHPUnit\Framework\TestCase;
include_once __DIR__ . '/../Presenter/tableRatrapage.php';
class test1 extends TestCase

{
    private $t;
    public function setUp(): void
    {
        $this->t = new tableRatrapage(1);
    }
    /** @test */
    public function testNbPages()
    {
        $totalPages = $this->t->getTotalPages();
        $this->assertIsInt($totalPages);
        $this->assertGreaterThanOrEqual(1, $totalPages);
    }
    /**@test */
    public function testNextPage()
    {
        $currentPage = $this->t->getPage();
        $this->t->nextPage();
        if ($currentPage < $this->t->getTotalPages() - 1) {
            $this->assertEquals($currentPage + 1, $this->t->getPage());
        } else {
            $this->assertEquals($currentPage, $this->t->getPage());
        }
    }
    /**@test */
    public function testPreviousPage()
    {
        $this->t->setPage(1); // Assurez-vous que nous ne sommes pas à la première page
        $currentPage = $this->t->getPage();
        $this->t->previousPage();
        if ($currentPage > 0) {
            $this->assertEquals($currentPage - 1, $this->t->getPage());
        } else {
            $this->assertEquals($currentPage, $this->t->getPage());
        }
    }
}