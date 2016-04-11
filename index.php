<?php
error_reporting(E_ALL);
set_time_limit (60);

function SplStacktoArray($ar) 
 {
  $array = [];
  foreach ($ar as $item) {
    $array[] = $item;
  }
  return $array;
}

class MPayParser {
    protected $stack;
    protected $file = "";
    protected $logFileH;
    protected $htmlFileH;
    protected $parser;
    protected $mysqli;
    protected $origEncoding;

    protected $values = [];
    protected $mrvalues = [];

    const HTML_IDENTATION = "  ";
    const BUFFER_SIZE = 512 * 1024;
    const OUTPUT_TEXT_FNAME = 'output.txt';

    public function __construct($file, $mysqli) {
        $this->stack = new SplStack();
        $this->file = $file;
        $this->mysqli = $mysqli;

        $this->origEncoding = [];
        $hePos = preg_match('/encoding="(.*)"/', file_get_contents($file, false, NULL, 0, 128), $origEncoding);
        $this->origEncoding = (@$this->origEncoding[1] ? $this->origEncoding[1] : 'UTF-8');
        $sourceEncoding = ($this->origEncoding == 'UTF-8')? $this->origEncoding : "ISO-8859-1";

        $this->parser = xml_parser_create($sourceEncoding);
        xml_set_object($this->parser, $this);
        xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, true);
        xml_set_element_handler($this->parser, "startTag", "endTag");
        xml_set_character_data_handler($this->parser, "contents");

    }

    public function startTag($parser, $name, $attribs) {
        $this->stack->push($name);
        fwrite($this->htmlFileH, '&lt;<span>'.$name.'</span>&gt;');
    }

    public function endTag($parser, $name) {
        $this->stack->pop();
        fwrite($this->htmlFileH, '&lt;/<span>'.$name.'</span>&gt;'); //todo: fwrite check
        switch($name) {
            case "MP":
                $tvalues = "";
                foreach($this->values as $k=>$v) {
                    // Ненадежные ключи, которые нужно отфильтровать
                    if ($k == 'SOURCE_PAYMENT') {
                        $this->mysqli->real_escape_string($v);
                    }
                }
                //Предполагается, что элементы внутри MP определены в xsd через  <xs:sequence> с дефолтными min/maxOccurs, т.е. всегда  представлены и следуют одному порядку
                $tvalues = implode(",", $this->values).PHP_EOL;
                fwrite($this->logFileH, $tvalues);
                $this->values = [];
                break;
            case "MPAY-RESPONSE":
                break;
        }
    }

    public function contents($parser, $text) {
        fwrite($this->htmlFileH, $text);
        if (!isset($this->stack[1])) return;
        $grandparent = $this->stack[1];
        $parent = $this->stack[0];

        switch ($grandparent) {
            case "MP":
                $this->values[$parent] = (isset($this->values[$parent]) ? $this->values[$parent] : "").$text;
                break;
                //mb_convert_encoding(fread($fin, self::BUFFER_SIZE), "UTF-8", $origEncoding))
            case "MPAY-RESPONSE":
                if ($parent=="MPS_PAY") break;
                $this->mrvalues[$parent] = (isset($this->mrvalues[$parent]) ? $this->mrvalues[$parent] : "").$text;
                break;
        }
    }

    public function parse() {
        $f = fopen($this->file, "r");
        if (!$f) exit("Unable to open file ".$this->file.PHP_EOL);
        $this->logFileH = fopen(self::OUTPUT_TEXT_FNAME, "w");
        if (!$this->logFileH) exit("Unable to open output text file".PHP_EOL);
        $this->htmlFileH = fopen($this->file.'.html', "w");
        if (!$this->htmlFileH) exit("Unable to open output html file".PHP_EOL);

        fwrite($this->htmlFileH, file_get_contents('header.html'));

        while (!feof($f)) {
            $data = fread($f, self::BUFFER_SIZE);
            if (!xml_parse($this->parser, $data, feof($f))) {
                die(sprintf("XML Error: %s at line %d", xml_error_string(xml_get_error_code($this->parser)), xml_get_current_line_number($this->parser)));
            }
        }
        fclose($f);
        fwrite($this->htmlFileH, '</body></html>');
        fclose($this->logFileH);
        fclose($this->htmlFileH);
        // if(!$this->mysqli->query('LOAD DATA LOCAL INFILE \''.$this->mysqli->real_escape_string(__DIR__.DIRECTORY_SEPARATOR.self::OUTPUT_TEXT_FNAME).'\' INTO TABLE `mp`
        //     FIELDS TERMINATED BY \',\' ESCAPED BY \'\\\\\' LINES TERMINATED BY \''.$this->mysqli->real_escape_string(PHP_EOL).'\'
        //     (`payid`, `pctransid`, @var1, `status`, `amount`, `fee`, `annul_amount`, `phone`, `goods_id`, `category_id`, `compensation_operator`, `trading_concession`, `branch`, `source_payment`)
        //     SET date = STR_TO_DATE(@var1, \'%d.%m.%Y %H:%i:%s\')')) {

        //         echo "MySql Errno: ".$this->mysqli->errno.PHP_EOL;
        //         echo "Error: ".$this->mysqli->error.PHP_EOL;
        //         exit();

        // }

        foreach($this->mrvalues as $k=>$v) {
            // Ненадежные ключи, которые нужно отфильтровать
            if ($k == 'RESULT') {
                $this->mysqli->real_escape_string($v);
            }
        }
        if(!$this->mysqli->query('INSERT INTO `mpay-response`(`result`, `timestamp`, `registerid`) VALUES ('.$this->mrvalues['RESULT'].',STR_TO_DATE(\''.$this->mrvalues['TIME_STAMP'].'\', \'%d.%m.%Y %H:%i:%s\'),'.$this->mrvalues['REGISTERID'].')')) {
                echo "MySql Errno: ".$this->mysqli->errno.PHP_EOL;
                echo "Error: ".$this->mysqli->error.PHP_EOL;
                exit();
        }

    }

    public function __destruct() {
        xml_parser_free($this->parser);
    }
}

$mysqli = new mysqli('127.0.0.1', 'root', '', 'x');

if ($mysqli->connect_errno) {
    die("Connection failed ".$mysqli->connect_error);
}

$parser = new MPayParser("8_7518_20160403.XML", $mysqli);
$parser->parse();
$mysqli->close();

?>
