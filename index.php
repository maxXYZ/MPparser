<?php
error_reporting(E_ALL);
set_time_limit (60);

//В реальном проекте этот класс нужно разделить на несколько и вынести некоторый функционал
//К тому-же, в данной реализации куча допущений, из-за которых "неправильные" входные данные могут что-нибуть поломать
class MPayParser {
    protected $stack;
    protected $file = "";
    protected $outputHtmlHeader = "";
    protected $textFileH;
    protected $htmlFileH;
    protected $parser;
    protected $mysqli;
    protected $origEncoding;

    protected $values = [];
    protected $mrvalues = [];

    const BUFFER_SIZE = 512 * 1024;
    const OUTPUT_TEXT_FNAME = 'output.txt'; // Изначально данный файл предполагался временным, но оставлен постоянным для целей отладки
    const OUTPUT_HTML_FNAME_SUFFIX = '.html';

    public function __construct($file, $htmlHeader, $mysqli) {
        $this->stack = new SplStack();
        $this->file = $file;
        $this->outputHtmlHeader = $htmlHeader;
        $this->mysqli = $mysqli;

        $this->origEncoding = [];
        $hePos = preg_match('/encoding="(.*)"/', file_get_contents($file, false, NULL, 0, 128), $origEncoding);
        $this->origEncoding = (@$this->origEncoding[1] ? $this->origEncoding[1] : 'UTF-8');
        $sourceEncoding = ($this->origEncoding == 'UTF-8')? $this->origEncoding : "ISO-8859-1";

        $this->parser = xml_parser_create($sourceEncoding);
        xml_set_object($this->parser, $this);
        xml_set_element_handler($this->parser, "startTag", "endTag");
        xml_set_character_data_handler($this->parser, "contents");
        xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, 0);

    }

    public function startTag($parser, $name, $attribs) {
        $this->stack->push($name);
        $html = '&lt;<span>'.$name;

        if (count($attribs)) {
            foreach ($attribs as $k => $v) {
                $html .= ' <span>$k</span>="<span>$v</span>"';
            }
        }

        $html .='</span>&gt;';
        if(fwrite($this->htmlFileH, $html) === false) {
            exit("Unable to write to text output file");
        }
    }

    public function endTag($parser, $name) {
        $this->stack->pop();
        if(fwrite($this->htmlFileH, '&lt;/<span>'.$name.'</span>&gt;') === false){
            exit("Unable to write to html output file");
        }

        switch($name) {
            case "mp":
                $tvalues = "";
                foreach($this->values as $k=>$v) {
                    // Ненадежные ключи, которые нужно отфильтровать
                    if ($k == 'source_payment') {
                        $this->mysqli->real_escape_string($v);
                    }
                }

                //Предполагается, что элементы внутри mp определены в xsd через  <xs:sequence> с дефолтными min/maxOccurs, т.е. всегда  представлены и следуют одному порядку
                $tvalues = implode(",", $this->values).PHP_EOL;

                if(fwrite($this->textFileH, $tvalues) === false) {
                    exit("Unable to write to text output file");
                }

                $this->values = [];
                break;
        }
    }

    public function contents($parser, $text) {
        mb_convert_encoding($text, "UTF-8", $this->origEncoding);
        if (fwrite($this->htmlFileH, '<b>'.$text.'</b>') === false) {
            exit("Unable to write to output html file");
        }
        if (!isset($this->stack[1])) return;
        $grandparent = $this->stack[1];
        $parent = $this->stack[0];

        switch ($grandparent) {
            case "mp":
                $this->values[$parent] = (isset($this->values[$parent]) ? $this->values[$parent] : "").$text;
                break;
            case "mpay-response":
                if ($parent=="mps_pay") break;
                $this->mrvalues[$parent] = (isset($this->mrvalues[$parent]) ? $this->mrvalues[$parent] : "").$text;
                break;
        }
    }

    public function parse() {
        $f = fopen($this->file, "r");
        if (!$f) exit("Unable to open input xml file ".$this->file.PHP_EOL);

        $this->textFileH = fopen(self::OUTPUT_TEXT_FNAME, "w");
        if (!$this->textFileH) exit("Unable to open output text file".PHP_EOL);

        $this->htmlFileH = fopen($this->file.self::OUTPUT_HTML_FNAME_SUFFIX, "w");
        if (!$this->htmlFileH) exit("Unable to open output html file".PHP_EOL);

        if (fwrite($this->htmlFileH, file_get_contents($this->outputHtmlHeader)) === false) {
            exit("Unable to write HTML Header to html output file");
        }

        while (!feof($f)) {
            $data = fread($f, self::BUFFER_SIZE);
            if (!xml_parse($this->parser, $data, feof($f))) {
                exit(sprintf("XML Error: %s at line %d", xml_error_string(xml_get_error_code($this->parser)), xml_get_current_line_number($this->parser)));
            }
        }
        fclose($f);
        if (fwrite($this->htmlFileH, '</body></html>') === false) {
            exit("Unable to write HTML Footer to html output file");
        }
        fclose($this->textFileH);
        fclose($this->htmlFileH);

        // if(!$this->mysqli->query('LOAD DATA LOCAL INFILE \''.$this->mysqli->real_escape_string(__DIR__.DIRECTORY_SEPARATOR.self::OUTPUT_TEXT_FNAME).'\' INTO TABLE `mp`
        //     FIELDS TERMINATED BY \',\' ESCAPED BY \'\\\\\' LINES TERMINATED BY \''.$this->mysqli->real_escape_string(PHP_EOL).'\'
        //     (`payid`, `pctransid`, @var1, `status`, `amount`, `fee`, `annul_amount`, `phone`, `goods_id`, `category_id`, `compensation_operator`, `trading_concession`, `branch`, `source_payment`)
        //     SET date = STR_TO_DATE(@var1, \'%d.%m.%Y %H:%i:%s\')')) {

        //         echo "MySql Errno: ".$this->mysqli->errno.'<br />'.PHP_EOL;
        //         echo "Error: ".$this->mysqli->error.PHP_EOL;
        //         exit();

        // }

        foreach($this->mrvalues as $k=>$v) {
            // Ненадежные ключи, которые нужно отфильтровать
            if ($k == 'result') {
                $this->mysqli->real_escape_string($v);
            }
        }
        if(!$this->mysqli->query('INSERT INTO `mpay-response`(`result`, `timestamp`, `registerid`) VALUES ('.$this->mrvalues['result'].',STR_TO_DATE(\''.$this->mrvalues['time_stamp'].'\', \'%d.%m.%Y %H:%i:%s\'),'.$this->mrvalues['registerid'].')')) {
                echo "MySql Errno: ".$this->mysqli->errno.'<br />'.PHP_EOL;
                echo "Error: ".$this->mysqli->error.PHP_EOL;
                exit();
        }
        
        //Здесь можно вывести результат, если оно надо, конечно
        //Этот код не для релиза, так что file_get_contents сойдет :)
        //echo file_get_contents($this->file.self::OUTPUT_HTML_FNAME_SUFFIX);

    }

    public function __destruct() {
        xml_parser_free($this->parser);
    }
}

$config = file_get_contents('config.xml');

if ($config === false) {
    exit("Unable to read config file");
}

$config = new SimpleXMLElement($config);


$mysqli = new mysqli($config->host, $config->db->username, $config->db->pass, $config->db->dbname);

if ($mysqli->connect_errno) {
    exit("Connection failed ".$mysqli->connect_error);
}



$parser = new MPayParser($config->xmlFilePath, $config->outputHtmlHeaderPath, $mysqli);
$parser->parse();
$mysqli->close();

?>
