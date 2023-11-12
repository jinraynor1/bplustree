<?php


namespace Jinraynor1\BplusTree\Nodes;


use Jinraynor1\BplusTree\Entries\Entry;
use Jinraynor1\BplusTree\Entries\Reference;
use Jinraynor1\BplusTree\TreeConf;

class ReferenceNode extends Node
{
    public function __construct(TreeConf $treeConf, $data = null, $page = null, $parent = null, $nextPage = null)
    {
        $this->entry_class = Reference::class;
        parent::__construct($treeConf, $data, $page, $parent, $nextPage);
    }

    public function numChildren()
    {
        return $this->entries ? count($this->entries) + 1 : 0;
    }

    /**
     * @param Reference|Entry $entry
     */
    public function insertEntry(Entry $entry)
    {
        /*
        Make sure that after of a reference matches before of the next one.
        Probably very inefficient approach.
        */
        parent::insertEntry($entry);
        $i = array_search($entry, $this->entries);
        if ($i > 0) {
            $previous_entry = $this->entries[$i - 1];
            $previous_entry->getAfter() == $entry->getBefore();
        }
        if (!isset($this->entries[$i + 1]))
            return;

        /**
         * @var $next_entry Reference
         */
        $next_entry = $this->entries[$i + 1];
        $next_entry->setBefore($entry->getAfter()) ;

    }
}