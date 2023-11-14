<?php


namespace Jinraynor1\BplusTree\Memory;


use Jinraynor1\BplusTree\Exceptions\ValueError;
use Jinraynor1\BplusTree\Primitives\Integer;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;


class WAL
{
    /**
     * @var int
     */
    private static $FRAME_HEADER_LENGTH;
    private $filename;
    private $pageSize;
    /**
     * @var mixed
     */
    private $fd;
    /**
     * @var mixed
     */
    private $dir_fd;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var array
     */
    /**
     * @var array
     */
    public  $committedPages;
    /**
     * @var array
     */
    public $notCommitedPages;
    /**
     * @var false
     */
    public $needsRecovery;

    public function __construct($filename, $pageSize)
    {

        self::$FRAME_HEADER_LENGTH = (
            FRAME_TYPE_BYTES + PAGE_REFERENCE_BYTES
        );
        $this->logger = new NullLogger();

        $this->filename = $filename.'-wal';
        list($this->fd, $this->dir_fd) = File::open_file_in_dir($this->filename);
        $this->pageSize = $pageSize;
        $this->committedPages = array();
        $this->notCommitedPages = array();
        fseek($this->fd, 0, SEEK_END);

        if (ftell($this->fd) == 0) {
            $this->createHeader();
            $this->needsRecovery = false;
        } else {
            $this->logger->warning('Found an existing WAL file, the B+Tree was not closed properly');
            $this->needsRecovery = true;
            $this->loadWal();
        }
    }

    /**
     * Transfer the modified data back to the tree and close the WAL.
     * @param $callback
     * @throws \Exception
     */
    public function checkpoint($callback)
    {
        if ($this->notCommitedPages) {
            $this->logger->warning('Closing WAL with uncommitted data, discarding it');
        }

        File::fsync_file_and_dir($this->fd, $this->dir_fd);

        foreach ($this->committedPages as $page => $pageStart) {
            $pageData = File::read_from_file($this->fd, $pageStart, $pageStart + $this->pageSize);
            call_user_func($callback, $page, $pageData);
        }

        fclose($this->fd);
        unlink($this->filename);
        if (!is_null($this->dir_fd)) {
            fflush($this->dir_fd);
            fclose($this->dir_fd);
        }
    }

    public function createHeader()
    {
        $data = pack("V",$this->pageSize);
        fseek($this->fd, 0);
        File::write_to_file($this->fd, $this->dir_fd, $data, true);
    }

    public function loadWal()
    {
        fseek($this->fd, 0);
        $header_data = File::read_from_file($this->fd, 0, OTHERS_BYTES);
        assert(Integer::fromBytes($header_data, ENDIAN) == $this->pageSize);

        while (true) {
            try {
                $this->loadNextFrame();
            } catch (\Exception $e) {
                break;
            }

        }
        if ($this->notCommitedPages) {
            $this->logger->warning('WAL has uncommitted data, discarding it');
            $this->notCommitedPages = array();
        }
    }

    public function loadNextFrame()
    {
        $start = ftell($this->fd);
        $stop = $start + self::$FRAME_HEADER_LENGTH;

        $data = File::read_from_file($this->fd, $start, $stop);

        $frame_type = Integer::fromBytes(py_slice($data, "0:" . FRAME_TYPE_BYTES), ENDIAN);
        $page = Integer::fromBytes(
            py_slice($data, FRAME_TYPE_BYTES . ":" . (FRAME_TYPE_BYTES + PAGE_REFERENCE_BYTES)),
            ENDIAN
        );

        if ($frame_type == FrameType::PAGE)
            fseek($this->fd, $stop + $this->pageSize);

        $this->indexFrame($frame_type, $page, $stop);
    }

    public function indexFrame($frameType, $page, $pageStart)
    {
        if ($frameType == FrameType::PAGE) {
            $this->notCommitedPages[$page] = $pageStart;
        } elseif ($frameType == FrameType::COMMIT) {
            $this->committedPages = $this->notCommitedPages + $this->committedPages;
            $this->notCommitedPages = array();
        } elseif ($frameType == FrameType::ROLLBACK) {
            $this->notCommitedPages = array();
        } else {
            assert(false);
        }
    }

    public function addFrame($frameType, $page = null, $pageData = null)
    {
        if ($frameType == FrameType::PAGE and (!$page or !$pageData))
            throw new ValueError('PAGE frame without page data');

        if ($pageData and strlen($pageData) != $this->pageSize)
            throw new ValueError('Page data is different from page size');

        if (!$page)
            $page = 0;

        if ($frameType != FrameType::PAGE)
            $pageData = '';
        $data = (
            pack("c",$frameType) .
            pack("V",$page) .
            $pageData
        );
        if(!is_resource($this->fd))
            throw new ValueError("supplied resource is not a valid stream resource");

        fseek($this->fd, 0, SEEK_END);

        File::write_to_file($this->fd, $this->dir_fd, $data,
            $frameType != FrameType::PAGE);

        $this->indexFrame($frameType, $page, ftell($this->fd) - $this->pageSize);
    }

    public function getPage($page)
    {
        $page_start = null;

        $allPages = $this->notCommitedPages + $this->committedPages;
        $page_start = isset($allPages[$page]) ? $allPages[$page] : null;


        if (!$page_start)
            return null;

        return File::read_from_file($this->fd, $page_start,
            $page_start + $this->pageSize);

    }

    public function setPage($page, $pageData)
    {
        $this->addFrame(FrameType::PAGE, $page, $pageData);
    }

    public function commit()
    {
        # Commit is a no-op when there is no uncommitted pages
        if ($this->notCommitedPages)
            $this->addFrame(FrameType::COMMIT);
    }

    public function rollback()
    {
        # Rollback is a no-op when there is no uncommitted pages
        if ($this->notCommitedPages)
            $this->addframe(FrameType::ROLLBACK);

    }

    public function represents()
    {
        return sprintf('<WAL: %s>', ($this->filename));

    }
}