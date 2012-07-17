<?php

namespace Knp\Bundle\MarkdownBundle\Twig\Extension;

use Knp\Bundle\MarkdownBundle\MarkdownParserInterface;

class MarkdownTwigExtension extends \Twig_Extension
{
    protected $parser;

    function __construct(MarkdownParserInterface $parser)
    {
        $this->parser = $parser;
    }

    public function getFilters()
    {
        return array(
            'markdown' => new \Twig_Filter_Method($this, 'markdown', array('is_safe' => array('html'))),
        );
    }

    public function markdown($txt)
    {
        return $this->parser->transform($txt);
    }

    public function getName()
    {
        return 'markdown';
    }
}
