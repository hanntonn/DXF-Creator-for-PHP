<?php
/**
 * Original script (DXF)
 * @author Alessandro Vernassa <speleoalex@gmail.com> http://speleoalex.altervista.org
 * @copyright Copyright (c) 2013
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License
 *
 * Upgrade script to "Creator"
 * @author Konstantin Kutsevalov <adamasantares@gmail.com>
 * @since 2015/08
 *
  *
 * @contributor Mario Fèvre https://github.com/mariofevre
 * @contributor azercon https://github.com/azercon
 * @contributor Michiel Vancoillie https://github.com/dive-michiel
 * @contributor Mangirdas Skripka https://github.com/maskas
 * @since 2015/08
 *
 * @see About DXF structure http://help.autodesk.com/cloudhelp/2016/ENU/AutoCAD-DXF/files/GUID-235B22E0-A567-4CF6-92D3-38A2306D73F3.htm
 * @see ENTITIES Section http://help.autodesk.com/cloudhelp/2016/ENU/AutoCAD-DXF/files/GUID-7D07C886-FD1D-4A0C-A7AB-B4D21F18E484.htm
 * @see Common Symbol Table Group Codes http://help.autodesk.com/cloudhelp/2016/ENU/AutoCAD-DXF/files/GUID-8427DD38-7B1F-4B7F-BF66-21ADD1F41295.htm
 *
 * Script upgraded to take into account multiple layouts, blocks, moving between layouts and blocks, add external image, filling shapes (HATCH) 
 * tried adding OLE2FRAME, but failed. Doesn't know how autocad encodes image binary to hex. 
 * Solution is to add external image with relative path and to include the picture in a directory with the dxf file to keep the image path valid even if the file is moved to another computer.
 * @author hanntonn <antoine.bou@gmail.com>
 * @after 2021/04
 *
 *
 * @example <code>
 *     $dxf = new \adamasantares\dxf\Creator( \adamasantares\dxf\Creator::INCHES );
  *	   $dxf->addLayout("fullView",1,279.4,431.8,$leftMargin,$topMargin,$leftMargin,$topMargin)
  *	   ->addLayout("partialView",1,279.4,431.8,$leftMargin,$topMargin,$leftMargin,$topMargin)
 *	   ->selectLayout("newLayout")
 *     ->addText(26, 46, 0, 'DXF testing', 8)
 *     ->setLayer('cyan', $color::CYAN)
 *     ->addLine(25, 0, 0, 100, 0, 0,0.500)
 *     ->addLine(100, 0, 0, 100, 75, 0,0.500)
 *     ->addLine(75, 100, 0, 0, 100, 0,0.500)
 *     ->addLine(0, 100, 0, 0, 25, 0,0.500)
 *     ->setLayer('blue', $color::BLUE, $ltype::DASHDOT)
 *     ->addCircle(0, 0, 0, 25)
 *     ->setLayer('custom', $color::rgb(10, 145, 230), $ltype::DASHED)
 *     ->addCircle(100, 100, 0, 25)
 *     ->setLayer('red', $color::RED)
 *     ->addArc(0, 100, 0, 25, 0.0, 270.0)
 *     ->setLayer('magenta', $color::MAGENTA)
 *     ->addArc(100, 0, 0, 25, 180.0, 90.0)
 *     ->setLayer('black')
 *     ->addPoint(0, 0, 0)
 *     ->addPoint(0, 100, 0)
 *     ->addPoint(100, 100, 0)
 *     ->addPoint(100, 0, 0)
 *	   ->addPolyline(array(0,14,0,-5,-3,0,0,0,0,0,14,0), 1,250)
 *	   ->addBlock("newBlock",0,0,0,0)
 *	   ->addInsert("newBlock",0,0,0,1,1,1,0)
 *     ->addMtext(0, -4, 0, 'N', 6)
 *     ->saveToFile('demo.dxf');
 * </code>
 */

namespace adamasantares\dxf;

// ini_set('display_errors',true);
/**
 * Class Creator
 * @package adamasantares\dxf
 */
class Creator {

	// units codes
    const UNITLESS = 0;
    const INCHES = 1;
    const FEET = 2;
    const MILES = 3;
    const MILLIMETERS = 4;
    const CENTIMETERS = 5;
    const METERS = 6;
    const KILOMETERS = 7;
    const MICROINCHES = 8;
    const MILS = 9;
    const YARDS = 10;
    const ANGSTROMS = 11;
    const NANOMETERS = 12;
    const MICRONS = 13;
    const DECIMETERS = 14;
    const DECAMETERS = 15;
    const HECTOMETERS = 16;
    const GIGAMETERS = 17;
    const ASTRONOMICAL_UNITS = 18;
    const LIGHT_YEARS = 19;
    const PARSECS = 20;

    /**
     * @var null Last error description
     */
    private $error = '';
	
	private $layouts = []; // pages of the document with layout name as index
	private $blocks = []; // blocks of shapes with block name as index
	private $objects=[]; // list of objects to add to objects section
	private $imageDict=[];
    /**
     * @var array Layers collection
     */
    private $layers = [];
	
    private $lTypes = [];
	
    private $textStyles = [];

    private $textStyleName = 'STANDARD';
    /**
     * Current layer name
     * @var int
     */
    private $layerName = '0';
	/*
	* current active layout
	*/
	private $firstLayout="";
	private $layoutName = '0';
	private $ACAD_IMAGE_DICT_HANDLE=77;
	private $blockName = '';
	private $patternName='SOLID'; // fill pattern for hatch see hatchPatternTypes.txt

    /**
     * @var array Center offset
     */
    private $offset = [0, 0, 0];

    /**
     * @var int Units
     */
    private $units = 0;
	
	private $leftMargin=4.233333;
	private	$topMargin=4.233333;
	private	$rightMargin=4.233333;
	private	$bottomMargin=4.233333;


    /**
     * @var string
     * A handle is a hexadecimal number that is a unique tag for each entity in a
     * drawing or DXF file. There must be no duplicate handles. The variable
     * HANDSEED must be larger than the largest handle in the drawing or DXF file.
     * @see https://forums.autodesk.com/t5/autocad-2000-2000i-2002-archive/what-is-the-handle-in-a-dxf-entity/td-p/118936
     */
    private $handleNumber = 0x4ff;
	//private $layoutBlockNumber="";
	private $layoutTabNumber=1;

    /**
     * @param int $units (MILLIMETERS as default value)
     * Create new DXF document
     */
    function __construct($units = self::MILLIMETERS)
    {
        $this->units = $units;
        // add default layout
        $this->addLayer($this->layerName);
    }
	
	/*
	* add new layout to document for multipage documents
	* orientation (1=landscape 0= portrait)
	* paperWidth (width of layout in mm)
	* paperHeight (height of layout in mm)
	* margin sizes 
	* printable area is $paperWidth-($marginLeft+$marginRight) and $paperHeight-($marginTop+$marginBottom)
	*/
	public function addLayout($name,$orientation=1,$paperWidth=279.4,$paperHeight=431.8,$marginTop=4.233333,$marginRight=4.233333,$marginBottom=4.233333,$marginLeft=4.233333){
		if(isset($this->layouts[$name])){
			return $this; // can't create two layouts with the same name
		}
		$this->leftMargin=$marginLeft;
		$this->rightMargin=$marginRight;
		$this->bottomMargin=$marginBottom;
		$this->topMargin=$marginTop;
		$this->blockName='*Paper_Space'.$this->getLayoutBlockNumber(); // name of block attached to layout
		$this->addBlock($this->blockName,0,0,0,0);
		$this->layouts[$name] = [
			'orientation' => $orientation,
			'paperWidth' => $paperWidth,
			'paperHeight' => $paperHeight,
			'marginTop' => $marginTop,
			'marginRight' => $marginRight,
			'marginBottom' => $marginBottom,
			'marginLeft' => $marginLeft,
			'handle'=>$this->getEntityHandle(), // handle of layout
			'ownerHandle'=>$this->blocks[$this->blockName]["blockRecordhandle"], // handle of block_record
			'blockName'=>$this->blockName,
			'viewportHandle'=>$this->getEntityHandle(),
			'tab'=>$this->layoutTabNumber++
		];
		$this->blocks[$this->blockName]["layoutHandle"]=$this->layouts[$name]["handle"]; // tell the block_record this layout is linked to it
		$this->layoutName = $name;
		if($this->firstLayout==""){
			$this->firstLayout=$name; // remember first layout to select it before exporting document
		}
		$this->addViewport($this->layouts[$name]["viewportHandle"]); // add viewport to the block linked to this layout
		$this->selectLayout($name);
		return $this;
	}
	/* set active block
	*/
	public function selectBlock($name){
		if (!isset($this->blocks[$name])) {
			return $this;
		}
		$this->blockName = $name;
        return $this;
	}
	
	/* set active layout
	
	
	*/
	public function selectLayout($name){
		if (!isset($this->layouts[$name])) {
			return $this;
		}
		$this->layoutName = $name;
		$this->selectBlock($this->layouts[$name]["blockName"]);
        return $this;
	}
    /**
     * Add new layer to document
     * @param string $name
     * @param int $color Color code (@see adamasantares\dxf\Color class)
     * @param string $lineType Line type (@see adamasantares\dxf\LineType class)
     * @return Creator Instance
     */
    public function addLayer($name, $color = Color::GRAY, $lineType = LineType::SOLID,$lineWeight=-3)
    {
		// lineweights possible:
		// -3	Default lineweight. -2	Lineweight defined by block. -1	Lineweight defined by layer.
		// 0:0.00 mm (hairline).,  5:0.05 mm. , 9:0.09 mm. ,  13:0.13 mm. , 15:0.15 mm. , 18:0.18 mm. , 20:0.20 mm. , 25:0.25 mm. , 30:0.30 mm. , 35:0.35 mm.
		// 40:0.40 mm. , 50:0.50 mm. , 53:0.53 mm. , 60:0.60 mm. , 70:0.70 mm. , 80:0.80 mm. , 90:0.90 mm. , 100:1.00 mm. , 106:1.06 mm. , 120:1.20 mm.
		//140:1.40 mm. , 158:1.58 mm. , 200:2.00 mm. , 211:2.11 mm.
        $this->layers[$name] = [
            'color' => $color,
            'lineType' => $lineType,
			'lineWeight' => $lineWeight
        ];
        $this->lTypes[$lineType] = $lineType;
        return $this;
    }


    /**
     * Sets current layer for drawing. If layer not exists than it will be created.
     * @param $name
     * @param int $color  (optional) Color code. Only for new layer (@see adamasantares\dxf\Color class)
     * @param string $lineType (optional) Only for new layer
     * @return Creator Instance
     */
    public function setLayer($name, $color = Color::GRAY, $lineType = LineType::SOLID,$lineWeight=-3)
    {
        if (!isset($this->layers[$name])) {
            $this->addLayer($name, $color, $lineType,$lineWeight);
        }
        $this->layerName = $name;
        return $this;
    }


    /**
     * Returns current layer name
     */
    public function getLayer()
    {
        return $this->layerName;
    }


    /**
     * Change color for current layer
     * @param int $color See adamasantares\dxf\Color constants
     * @return Creator Instance
     */
    public function setColor($color)
    {
        $this->layers[$this->layerName]['color'] = $color;
        return $this;
    }
	public function addBlock($blockName,$layerName=0,$baseX,$baseY,$baseZ){
		$this->blocks[$blockName] = [
			'blockRecordhandle'=>$this->getEntityHandle(),
	//		'blockRecordhandle'=>'21',
			'handle'=>$this->getEntityHandle(),
	//		'handle'=>'22',
			'handleEnd'=>$this->getEntityHandle(),
		//	'handleEnd'=>'23',
			'ownerHandle'=>1, // handle of first block_record
			'layer' => $layerName,
			'layoutHandle'=> 0,    // option 340 of block_Record
			'baseX' => $baseX,
			'baseY' => $baseY,
			'baseZ' => $baseZ,
			'shapes' => [],
			'blkrefs'=>[]
		];
		$this->selectBlock($blockName);
		return $this;
	}
	/*
	insert block into any other block at specified position
	$dxf->addInsert($blockName,$x,$y,$z,$scaleX,$scaleY,$scaleZ)
	// rotation angle in degrees
	*/
	public function addInsert($blockName,$x,$y,$z,$scaleX=1,$scaleY=1,$scaleZ=1,$rotationAngle=0){
		if(!isset($this->blocks[$blockName])){
			return $this;
		}
		$x += $this->offset[0];
        $y += $this->offset[1];
        $z += $this->offset[2];
		$handle=$this->getEntityHandle();
		$this->blocks[$this->blockName]["shapes"][] = "0\n" .
		"INSERT\n" .
		  "5\n" . // Entity Handle
		$handle."\n" .
		"330\n" .
		$this->blocks[$this->blockName]["blockRecordhandle"]."\n" . // Soft-pointer ID/handle to owner object
		"100\n" .
		"AcDbEntity\n" .
		"67\n".
		"1\n".
		"8\n" .
		"{$this->layerName}\n" .
		"100\n" .
		"AcDbBlockReference\n" .
		"2\n" .
		"{$blockName}\n" .
		"10\n" .
		"{$x}\n" .
		"20\n" .
		"{$y}\n" .
		"30\n" .
		"{$z}\n".
		"41\n". // x scale
		$scaleX."\n".
		"42\n". // y scale
		$scaleY."\n".
		"43\n". // z scale
		$scaleZ."\n".
		"50\n". // rotation angle
		$rotationAngle."\n";
		array_push($this->blocks[$blockName]["blkrefs"],$handle);
		return $this;
	}
    /**
     * Change line type for current layer
     * @param int $lineType See adamasantares\dxf\LineType constants
     * @return Creator Instance
     */
    public function setLineType($lineType)
    {
        $this->layers[$this->layerName]['lineType'] = $lineType;
        $this->lTypes[$lineType] = $lineType;
        return $this;
    }
	/**
	private function getLayoutBlockNumber(){
     * Sets current style for drawing. If style does not exist then it will be created.
		if(!isset($this->layoutBlockNumber)){
     * @param string $params [name, font]
			$this->layoutBlockNumber=1;
     * @return Creator Instance
			return "";
     */
    public function setTextStyle($name, $font, $stdFlags = 0, $fixedHeight = 0, $widthFactor = 0, $obliqueAngle = 0, $textGenerationFlags = 0, $lastHeightUsed = 0, $bigFont = null)
    {
        if ( !isset($this->textStyles[$name]) ) {
            $this->textStyles[$name] = [
                'name' => $name,
                'font' => $font,
                'stdFlags' => $stdFlags,
                'fixedHeight' => $fixedHeight,
                'widthFactor' => $widthFactor,
                'obliqueAngle' => $obliqueAngle,
                'textGenerationFlags' => $textGenerationFlags,
                'lastHeightUsed' => $lastHeightUsed,
                'bigFont' => $bigFont,
            ];
        }
        $this->textStyleName = $name;
        return $this;
    }


    /**
     * Returns current style name
     */
    public function getTextStyle()
    {
        return $this->textStyleName;
    }
	private function getLayoutBlockNumber(){
		if(!isset($this->layoutBlockNumber)){
			$this->layoutBlockNumber=1;
			return "";
		}
		return $this->layoutBlockNumber++;
	}
    private function getEntityHandle()
    {
        $this->handleNumber++;
        return dechex($this->handleNumber);
    }


    /**
     * Add point to current block or current layout
     * @param float $x
     * @param float $y
     * @param float $z
     * @return Creator Instance
     * @see http://help.autodesk.com/cloudhelp/2016/ENU/AutoCAD-DXF/files/GUID-9C6AD32D-769D-4213-85A4-CA9CCB5C5317.htm
     */
    public function addPoint($x, $y, $z)
    {
        $x += $this->offset[0];
        $y += $this->offset[1];
        $z += $this->offset[2];
		$handle=$this->getEntityHandle();
        $this->blocks[$this->blockName]["shapes"][] = "0\n".
			"POINT\n" .
            "5\n" . // Entity Handle
            $handle."\n" .
			"330\n" .
			$this->blocks[$this->blockName]["blockRecordhandle"]."\n" .
            "100\n" . // Subclass marker (AcDbEntity)
            "AcDbEntity\n" .
			"67\n".
			"1\n".
            "8\n" . // Layer name
            "{$this->layerName}\n" .
            "100\n" . // Subclass marker (AcDbPoint)
            "AcDbPoint\n" .
            "10\n" . // X value
            "{$x}\n" .
            "20\n" . // Y value
            "{$y}\n" .
            "30\n" . // Z value
            "{$z}\n"; // ici y'avait  un 0 de trop a la fin (le zero doit etre ajoute au debut pour chaque element cree)
        return $this;
    }


    /**
     * Add line to current block or current layout
     * @param float $x
     * @param float $y
     * @param float $z
     * @param float $x2
     * @param float $y2
     * @param float $z2
     * @return Creator Instance
     * @see http://help.autodesk.com/cloudhelp/2016/ENU/AutoCAD-DXF/files/GUID-FCEF5726-53AE-4C43-B4EA-C84EB8686A66.htm
     */
    public function addLine($x, $y, $z, $x2, $y2, $z2,$thickness=0)
    {
        $x += $this->offset[0];
        $y += $this->offset[1];
        $z += $this->offset[2];
        $x2 += $this->offset[0];
        $y2 += $this->offset[1];
        $z2 += $this->offset[2];
		$handle=$this->getEntityHandle();
        $this->blocks[$this->blockName]["shapes"][] = "0\n" .
			"LINE\n" .
            "5\n" . // Entity Handle
            $handle."\n" .
			"330\n" .
		//	"21\n".
			$this->blocks[$this->blockName]["blockRecordhandle"]."\n" .
            "100\n" . // Subclass marker (AcDbEntity)
            "AcDbEntity\n" .
			"67\n".
			"1\n".
            "8\n" . // Layer name
		//	"block1_layer\n".
            "{$this->layerName}\n" .
            "100\n" .
            "AcDbLine\n" . // Subclass marker (AcDbLine)
            "10\n" . // Start point X
            "{$x}\n" .
            "20\n" . // Start point Y
            "{$y}\n" .
            "30\n" . // Start point Z
            "{$z}\n" .
            "11\n" . // End point X
            "{$x2}\n" .
            "21\n" . // End point Y
            "{$y2}\n" .
            "31\n" . // End point Z
            "{$z2}\n" .
			"39\n" .
			"{$thickness}\n";
        return $this;
    }

    /**
     * Add a solid to the current block or current layout
     * @param float $x
     * @param float $y
     * @param float $z
     * @param float $w
     * @param float $h
     * @return Creator $this
     * @see http://help.autodesk.com/view/ACD/2016/ENU/?guid=GUID-E0C5F04E-D0C5-48F5-AC09-32733E8848F2
     */
    public function addSolid($x, $y, $z=0.0, $w=0.0, $h=0.0,$fill=0)
    {
		// this is just for squares. Modify if you want to include more shapes.
        $y1 = $y+$h;
        $x1 = $x+$w;
		$handle=$this->getEntityHandle();
        $this->blocks[$this->blockName]["shapes"][] = "0\n".
			"SOLID\n" .
            "5\n" . // Entity Handle
            $handle."\n" .
			"330\n" .
			$this->blocks[$this->blockName]["blockRecordhandle"]."\n" .
            "100\n" . // Subclass marker (AcDbEntity)
            "AcDbEntity\n" .
			"67\n".
			"1\n".
            "8\n" . // Layer name
            "{$this->layerName}\n" .
            "100\n" . // Subclass marker (AcDbTrace)
            "AcDbTrace\n" .
            "10\n" . // bottom-right corner, X 
            "{$x1}\n" .
            "20\n" . // bottom-right corner, Y
            "{$y}\n" .
            "30\n" . // bottom-right corner, Z
            "{$z}\n" .
            "11\n" . // top-right corner, X
            "{$x1}\n" .
            "21\n" . // top-right corner, Y
            "{$y1}\n" .
            "31\n" . // top-right corner, Z
            "{$z}\n" .
            "12\n" . // bottom-left corner, X
            "{$x}\n" .
            "22\n" . // bottom-left corner, Y
            "{$y}\n" .
            "32\n" . // bottom-left corner, Z
            "{$z}\n" .
            "13\n" . // top-left corner, X
            "{$x}\n" .
            "23\n" . // top-left corner, Y
            "{$y1}\n" .
            "33\n" . // top-left corner, Z
            "{$z}\n" .
            "39\n" . // Thickness
            "0\n" .
            "210\n". // Extrusion Direction, X
            "0\n" .
            "220\n". // Extrusion Direction, Y
            "0\n" .
            "230\n". // Extrusion Direction, Z
            "1\n";
        return $this;
    }
	/**
	 * Add multiline text to current block or current layout
	 * @param float $x insertion point x
	 * @param float $y insertion point y
	 * @param float $z insertion point z
	 * @param string or array $text multiline text array or string separated by \n or \P
	 * the \n will be converted to \P and if it is an array it will be imploded with \P as separator
	 * @param float $textHeight Text height
	 * @param integer $position Position of text from point: 1 = top-left; 2 = top-center; 3 = top-right; 4 = center-left; 5 = center; 6 = center-right; 7 = bottom-left; 8 = bottom-center; 9 = bottom-right
	 * @param float $angle Angle of text in degrees (rotation)
	 * background color not implemented didn't work in nanocad
	 * @param string $textStyle name of text style ex: @Arial Unicode MS, Arial, Calibri see a cad program for more examples
	 * @param float @textSize // indicate text size in hundredth of milimiter
	 * -1 = BYLAYER -2 = BYBLOCK -3 = DEFAULT Other valid values entered in hundredths of millimeters include 0, 5, 9, 13, 15, 18, 20, 25, 30, 35, 40, 50, 53, 60, 70, 80, 90, 100, 106, 120, 140, 158, 200, and 211.
	 * @param float $underline 0=not underlined, 1=underlined
	 * @return Creator Instance
	 */
	public function addMtext($x,$y,$z,$text,$textSize=-1,$textBoxWidth=0,$textBoxHeight=0,$underlined=0,$position = 5, $angle=0.0,$textStyle="Arial"){
		if(is_array($text)){
			$text=implode("\P",$text);
		}
		else{
			$text=str_replace('\n','\P',$text);
		}
		if(!preg_match('!!u', $text)) {
			$text=utf8_encode($text);
		}
		if($underlined=="1"){
			$text='\L'.$text;
		}
		$x += $this->offset[0];
        $y += $this->offset[1];
        $z += $this->offset[2];
        //$angle = deg2rad($angle);
		$handle=$this->getEntityHandle();
		$this->blocks[$this->blockName]["shapes"][] = "0\n" .
		"MTEXT\n" .
		"5\n" .
		$handle."\n" .
		"330\n" .
		$this->blocks[$this->blockName]["blockRecordhandle"]."\n" .
		"100\n" .
		"AcDbEntity\n" .
		"67\n" .
		"1\n" .
		"8\n" .
		$this->layerName."\n" .
		//"370\n". // text size
		//$textSize."\n".
		"100\n" .
		"AcDbMText\n" .
		"10\n" . // First alignment point, X value
		$x."\n" .
		"20\n" . // First alignment point, Y value
		$y."\n" .
		"30\n" . // First alignment point, Y value
		$z."\n" .
		"40\n" . // original text height
		$textSize."\n" .
		"71\n" . // Attachment point: 1 = Top left; 2 = Top center; 3 = Top right 4 = Middle left; 5 = Middle center; 6 = Middle right 7 = Bottom left; 8 = Bottom center; 9 = Bottom right
		$position."\n" .
		"72\n" . // Drawing direction:
		"1\n" .
		"1\n" . // text  {\fArial Black|b0|i0|c0|p34;ddfdf\Psddds}
		//'\pxqc;{\f'.$textStyle."|b0|i0|c0|p34;".$text."}\n" .
		'{\f'.$textStyle."|b0|i0|c0|p34;".$text."}\n" .
		"42\n".
		$textBoxWidth."\n" .
		"43\n".
		$textBoxHeight."\n" .
		"50\n".
		$angle."\n".
		"73\n" .
		"1\n" .
		"44\n" .
		"1.0\n";
		/*
		"101\n".
		"Embedded Object\n".
		"70\n".
		"1\n".
		"10\n".
		"1.0\n".
		"20\n".
		"0.0\n".
		"30\n".
		"0.0\n".
		"11\n".
		"89.62504259418119\n".
		"21\n".
		"48.97507957966865\n".
		"31\n".
		"0.0\n".
		"40\n".
		"77.46057755061838\n".
		"41\n".
		"0.0\n".
		"42\n".
		"0.7261937244201912\n".
		"43\n".
		"0.540018190086403\n".
		"71\n".
		"2\n".
		"72\n".
		"1\n".
		"44\n".
		"77.46057755061838\n".
		"45\n".
		"1.0\n".
		"73\n".
		"0\n".
		"74\n".
		"0\n".
		"46\n".
		"0.0\n";*/
/*
		"1001\n" .
		"ACAD\n" .
		"1000\n" .
		"ACAD_MTEXT_DEFINED_HEIGHT_BEGIN\n" .
		"1070\n" .
		"46\n" .
		"1040\n" .
		"9.0\n" .
		"1000\n" .
		"ACAD_MTEXT_DEFINED_HEIGHT_END\n";*/

		return $this;
	}
    /**
     * Add text to current block or current layout
     * @param float $x
     * @param float $y
     * @param float $z
     * @param string $text
     * @param float $textHeight Text height
     * @param integer $position Position of text from point: 1 = top-left; 2 = top-center; 3 = top-right; 4 = center-left; 5 = center; 6 = center-right; 7 = bottom-left; 8 = bottom-center; 9 = bottom-right
     * @param float $angle Angle of text in degrees (rotation)
     * @param integer $thickness
     * @return Creator Instance
     * @see http://help.autodesk.com/cloudhelp/2016/ENU/AutoCAD-DXF/files/GUID-62E5383D-8A14-47B4-BFC4-35824CAE8363.htm
     */
    public function addText($x, $y, $z, $text, $textHeight, $position = 5, $angle = 0.0, $thickness = 0)
    {
        $positions = [
            1 => [3, 0], // top-left
            2 => [3, 1], // top-center
            3 => [3, 2], // top-right
            4 => [2, 0], // center-left
            5 => [2, 1], // center
            6 => [2, 2], // center-right
            7 => [1, 0], // bottom-left
            8 => [1, 1], // bottom-center
            9 => [1, 2]  // bottom-right
        ];
        $x += $this->offset[0];
        $y += $this->offset[1];
        $z += $this->offset[2];
        $angle = deg2rad($angle);
        $verticalJustification = $positions[$position][0];
        $horizontalJustification = $positions[$position][1];
		$handle=$this->getEntityHandle();
        $this->blocks[$this->blockName]["shapes"][] = "0\n".
			"TEXT\n" .
            "5\n" . // Entity Handle
            $handle."\n" .
			"330\n" .
			$this->blocks[$this->blockName]["blockRecordhandle"]."\n" .
            "100\n" . // Subclass marker (AcDbEntity)
            "AcDbEntity\n" .
			"67\n".
			"1\n".
            "8\n" . // Layer name
            "{$this->layerName}\n" .
            "100\n" . // Subclass marker (AcDbText)
            "AcDbText\n" .
            "39\n" . // Thickness (optional; default = 0)
            "{$thickness}\n" .
            "10\n" . // First alignment point, X value
            "{$x}\n" .
            "20\n" . // First alignment point, Y value
            "{$y}\n" .
            "30\n" . // First alignment point, Z value
            "{$z}\n" .
            "40\n" . // Text height
            "{$textHeight}\n" .
            "1\n" . // Default value (the string itself)
            "{$text}\n" .
            "50\n" . // Text rotation (optional; default = 0)
            "{$angle}\n" .
            "41\n" . // Relative X scale factor—width (optional; default = 1)
            "1\n" .
            "51\n" . // Oblique angle (optional; default = 0)
            "0\n" .
            "7\n" . // Text style name (optional, default = STANDARD)
            "{$this->textStyleName}\n" .
            "71\n" . // Text generation flags (optional, default = 0)
            "0\n" .
            "72\n" . // Horizontal text justification type (optional, default = 0) integer codes (not bit-coded): 0 = Left, 1= Center, 2 = Right, 3 = Aligned, 4 = Middle, 5 = Fit
            "{$horizontalJustification}\n" .
            "11\n" . // Second alignment point, X value
            "{$x}\n" .
            "21\n" . // Second alignment point, Y value
            "{$y}\n" .
            "31\n" . // Second alignment point, Z value
            "{$z}\n" .
            "100\n" . // Subclass marker (AcDbText)
            "AcDbText\n" .
            "73\n" . // Vertical text justification type (optional, default = 0): integer codes (not bit-coded): 0 = Baseline, 1 = Bottom, 2 = Middle, 3 = Top
            "{$verticalJustification}\n";
        return $this;
    }


    /**
     * Add circle to current block or current layout
     * @param float $x
     * @param float $y
     * @param float $z
     * @param float $radius
     * @return Creator Instance
     * @see http://help.autodesk.com/cloudhelp/2016/ENU/AutoCAD-DXF/files/GUID-8663262B-222C-414D-B133-4A8506A27C18.htm
     */
    public function addCircle($x, $y, $z, $radius,$fill=0)
    {
        $x += $this->offset[0];
        $y += $this->offset[1];
        $z += $this->offset[2];
		$handle=$this->getEntityHandle();
        $this->blocks[$this->blockName]["shapes"][] = "0\n".
			"CIRCLE\n" .
            "5\n" . // Entity Handle
            $handle."\n" .
			"330\n" .
			$this->blocks[$this->blockName]["blockRecordhandle"]."\n" .
            "100\n" . // Subclass marker (AcDbEntity)
            "AcDbEntity\n" .
			"67\n".
			"1\n".
            "8\n" . // Layer name
            $this->layerName."\n" .
            "100\n" . // Subclass marker (AcDbCircle)
            "AcDbCircle\n" .
            "10\n" . // Center point, X value
            "{$x}\n" .
            "20\n" . // Center point, Y value
            "{$y}\n" .
            "30\n" . // Center point, Z value
            "{$z}\n" .
            "40\n" . // Radius
            "{$radius}\n";
		if($fill!==0){
			$this->addHatch("circular",$handle,$x,$y,$z,array($radius),$fill);
		}
        return $this;
    }


    /**
     * Add Arc to current block or current layout
     * Don't forget: it's drawing by counterclock-wise.
     * @param float $x
     * @param float $y
     * @param float $z
     * @param float $radius
     * @param float $startAngle
     * @param float $endAngle
     * @return $this
     * @see http://help.autodesk.com/cloudhelp/2016/ENU/AutoCAD-DXF/files/GUID-0B14D8F1-0EBA-44BF-9108-57D8CE614BC8.htm
     */
    public function addArc($x, $y, $z, $radius, $startAngle = 0.1, $endAngle = 90.0)
    {
        $x += $this->offset[0];
        $y += $this->offset[1];
        $z += $this->offset[2];
		$handle=$this->getEntityHandle();
        $this->blocks[$this->blockName]["shapes"][] = "0\n".
			"ARC\n" .
            "5\n" . // Entity Handle
            $handle."\n" .
			"330\n" .
			$this->blocks[$this->blockName]["blockRecordhandle"]."\n" .
            "100\n" . // Subclass marker (AcDbEntity)
            "AcDbEntity\n" .
			"67\n".
			"1\n".
            "8\n" . // Layer name
            "{$this->layerName}\n" .
            "100\n" . // Subclass marker (AcDbCircle)
            "AcDbCircle\n" .
            "39\n" . // Thickness (optional; default = 0)
            "0\n" .
            "10\n" . // Center point, X value
            "{$x}\n" .
            "20\n" . // Center point, Y value
            "{$y}\n" .
            "30\n" . // Center point, Z value
            "{$z}\n" .
            "40\n" . // Radius
            "{$radius}\n" .
            "100\n" . // Subclass marker (AcDbArc)
            "AcDbArc\n" .
            "50\n" . // Start angle
            "{$startAngle}\n" .
            "51\n" . // End angle
            "{$endAngle}\n";
        return $this;
    }


    /**
     * Add Ellipse to current block or current layout
     * @param float $cx Center Point X
     * @param float $cy Center Point Y
     * @param float $cz Center Point Z
     * @param float $mx Major Axis Endpoint X
     * @param float $my Major Axis Endpoint Y
     * @param float $mz Major Axis Endpoint Z
     * @param float $ratio Ratio of minor axis to major axis
	 * @param $start= start angle from end point turning counterclock-wise /180*PI (90 degrees equals PI/2)
	 * @param $end= end angle from end point turning counterclock-wise
	 * line $cx,$cy,$cz to $mx,$my,$mz creates rotation to the ellipse affecting position of start, end angles
     * @return $this
     * @see https://raw.githubusercontent.com/active-programming/DXF-Creator-for-PHP/master/demo/ellipse2.png
     * @see http://help.autodesk.com/cloudhelp/2016/ENU/AutoCAD-DXF/files/GUID-107CB04F-AD4D-4D2F-8EC9-AC90888063AB.htm
     */
    public function addEllipse($cx, $cy, $cz, $mx, $my, $mz, $ratio=0.5, $start = 0, $end = 6.283185307179586,$fill=0)
    {
        $mx -= $cx;
        $my -= $cy;
        $mz -= $cz;
		$handle=$this->getEntityHandle();
        $this->blocks[$this->blockName]["shapes"][] = "0\n".
			"ELLIPSE\n" .
            "5\n" . // Entity Handle
            $handle."\n" .
			"330\n" .
			$this->blocks[$this->blockName]["blockRecordhandle"]."\n" .
            "100\n" . // Subclass marker (AcDbEntity)
            "AcDbEntity\n" .
			"67\n".
			"1\n".
            "8\n" . // Layer name
            "{$this->layerName}\n" .
            "100\n" . // Subclass marker (AcDbEllipse)
            "AcDbEllipse\n" .
            "10\n" . // Center point, X value
            "{$cx}\n" .
            "20\n" . // Center point, Y value
            "{$cy}\n" .
            "30\n" . // Center point, Z value
            "{$cz}\n" .
            "11\n" . // Endpoint of major axis, X value
            "{$mx}\n" .
            "21\n" . // Endpoint of major axis, Y value
            "{$my}\n" .
            "31\n" . // Endpoint of major axis, Z value
            "{$mz}\n" .
            "40\n" . // Ratio of minor axis to major axis
            "{$ratio}\n" .
            "41\n" . // Start parameter (this value is 0.0 for a full ellipse)
            "{$start}\n" .
            "42\n" . // End parameter (this value is 2pi for a full ellipse)
            "{$end}\n";
		if($fill!=0){
			$this->addHatch("elliptic",$handle,$cx,$cy,$cz,array($mx,$my,$mz,$ratio,$start,$end),$fill);
		}
        return $this;
    }


    /**
     * Add Ellipse to current block or current layout
     * @param float $cx Center Point X
     * @param float $cy Center Point Y
     * @param float $cz Center Point Z
     * @param float $mx Major Axis Endpoint X
     * @param float $my Major Axis Endpoint Y
     * @param float $mz Major Axis Endpoint Z
     * @param float $rx Minor Axis Endpoint X
     * @param float $ry Minor Axis Endpoint Y
     * @param float $rz Minor Axis Endpoint Z
     *
     * @return $this
     * @see https://raw.githubusercontent.com/active-programming/DXF-Creator-for-PHP/master/demo/ellipse.png
     * @see http://help.autodesk.com/cloudhelp/2016/ENU/AutoCAD-DXF/files/GUID-107CB04F-AD4D-4D2F-8EC9-AC90888063AB.htm
     */
    public function addEllipseBy3Points($cx, $cy, $cz, $mx, $my, $mz, $rx, $ry, $rz, $start = 0, $end = 6.283185307179586,$fill=0)
    {
        $length1 = sqrt(pow($cx - $mx, 2) + pow($cy - $my, 2) + pow($cz - $mz, 2));
        $length2 = sqrt(pow($cx - $rx, 2) + pow($cy - $ry, 2) + pow($cz - $rz, 2));
        $ratio = round($length2 / $length1, 3);
        $mx -= $cx;
        $my -= $cy;
        $mz -= $cz;
		$handle=$this->getEntityHandle();
        $this->blocks[$this->blockName]["shapes"][] = "0\n".
			"ELLIPSE\n" .
            "5\n" . // Entity Handle
            $handle."\n" .
			"330\n" .
			$this->blocks[$this->blockName]["blockRecordhandle"]."\n" .
            "100\n" . // Subclass marker (AcDbEntity)
            "AcDbEntity\n" .
			"67\n".
			"1\n".
            "8\n" . // Layer name
            "{$this->layerName}\n" .
            "100\n" . // Subclass marker (AcDbEllipse)
            "AcDbEllipse\n" .
            "10\n" . // Center point, X value
            "{$cx}\n" .
            "20\n" . // Center point, Y value
            "{$cy}\n" .
            "30\n" . // Center point, Z value
            "{$cz}\n" .
            "11\n" . // Endpoint of major axis, X value
            "{$mx}\n" .
            "21\n" . // Endpoint of major axis, Y value
            "{$my}\n" .
            "31\n" . // Endpoint of major axis, Z value
            "{$mz}\n" .
            "40\n" . // Ratio of minor axis to major axis
            "{$ratio}\n" .
            "41\n" . // Start parameter (this value is 0.0 for a full ellipse)
            "{$start}\n" .
            "42\n" . // End parameter (this value is 2pi for a full ellipse)
            "{$end}\n";
		if($fill!=0){
			$this->addHatch("elliptic",$handle,$cx,$cy,$cz,array($mx,$my,$mz,$ratio,$start,$end),$fill);
		}
        return $this;
    }


    /**
     * Add polyline to current block or current layout
	 * Add 3D polyline to current block or current layout
     * @param array[float] $points Points array: [x, y, z, x2, y2, z2, x3, y3, z3, ...]
     * @param int $flag Polyline flag (bit-coded); default is 0: 1 = Closed; 128 = Plinegen
     * @return $this
     * @see http://help.autodesk.com/cloudhelp/2016/ENU/AutoCAD-DXF/files/GUID-ABF6B778-BE20-4B49-9B58-A94E64CEFFF3.htm
     */
    public function addPolyline($points, $flag = 0,$fill=0,$thickness=0)
    {
        $count = count($points);
        if ($count > 3 && ($count % 3) == 0) {
            $dots = ($count / 3 + 1);
			$handle=$this->getEntityHandle();
            $polyline = "0\n".
				"LWPOLYLINE\n" .
                "5\n" . // Entity Handle
                $handle."\n" .
				"330\n" .
				$this->blocks[$this->blockName]["blockRecordhandle"]."\n" .
                "100\n" . // Subclass marker (AcDbEntity)
                "AcDbEntity\n" .
				"67\n".
				"1\n".
                "8\n" . // Layer name
                $this->layerName."\n" .
                "100\n" . // Subclass marker (AcDbPolyline)
                "AcDbPolyline\n" .
                "90\n" . // Number of vertices
                "{$dots}\n" .
                "70\n" . // Polyline flag (bit-coded); default is 0: 1 = Closed; 128 = Plinegen
                "{$flag}\n" .
                "38\n" . // Elevation (optional; default = 0)
                "0\n" .
                "39\n" . // Thickness (optional; default = 0) // sera ignore si 43 est defini
                "{$thickness}\n" .
				"43\n" . // Constant width (optional; default = 0).
                "{$thickness}\n";
            for ($i = 0; $i < $count; $i += 3) {
                $x = $points[$i] + $this->offset[0];
                $y = $points[$i+1] + $this->offset[1];
				$z =$points[$i+2] + $this->offset[1];
                $polyline .=
                    "10\n" .
                    "{$x}\n" .
                    "20\n" .
                    "{$y}\n" .
					"30\n" . // Center point, Z value
					"{$z}\n";
            }
            $this->blocks[$this->blockName]["shapes"][] = $polyline;
			if($fill!=0 && $flag!=0){
				$points2d=array();
				$firstX=$points[0];
				$firstY=$points[1];
				for ($i = 0; $i < $count; $i += 3) {
					array_push($points2d,$points[$i]);
					array_push($points2d,$points[$i+1]);
				}
				//array_push($points2d,$firstX);
				//array_push($points2d,$firstY);
				$this->addHatch("polyline",$handle,0,0,$z,$points2d,$fill); // unable to fill polyline
			}
        }
        return $this;
    }


    /**
     * @return $this
     * @param array[float] $points Points array: [x, y, x2, y2, x3, y3, ...]
     * @deprecated It was mistake, the polyline has no Z coordinate point (code 30)
	 * 2021/05 Z coordinate added
     */
    public function addPolyline2d($points,$z,$fill=0)
    {
		$count = count($points);
		$points3d=array();
		if ($count > 2 && ($count % 2) == 0) {
			$dots = ($count / 2 + 1);
			for ($i = 0; $i < $count; $i += 2) {
                array_push($points3d,$points[$i]);
                array_push($points3d,$points[$i+1]);
				array_push($points3d,$z);
			}
		}
        return $this->addPolyline($points3d,$fill);
    }
	 /**
     * Add hatch to fill valid shape in current block or current layout 
     * @return $this
     */
	public function addHatch($type,$parentHandle,$x,$y,$z,$points,$colorCode=0){ // fill polygon with pattern color for list of hatch patterns, see hatchPatternTypes.txt
		// type supported 1 = line; 2 = circular; 3 = elliptic      not supported 4 = spline
		$arrayTypes=array("line"=>"1","circular"=>"2","elliptic"=>"3","spline"=>"4","polyline"=>"1");
		if(!isset($arrayTypes[$type]) || $type=="spline"){
			return $this;
		}
		$typeNumber=$arrayTypes[$type];
		$handle=$this->getEntityHandle();
		$hatch="0\n".
		"HATCH\n".
		"5\n".
		$handle."\n".
		"330\n".
		$this->blocks[$this->blockName]["blockRecordhandle"]."\n".
		"100\n".
		"AcDbEntity\n".
		"67\n".
		"1\n".
		"8\n".
		$this->layerName."\n".
		"62\n".
		$colorCode."\n".
		"100\n".
		"AcDbHatch\n".
		"10\n". // x value of Elevation point
		"0.0\n".
		"20\n". // y value of Elevation point
		"0.0\n".
		"30\n". // z value of Elevation point
		"$z\n".
		"210\n". // x value of extrusion direction
		"0.0\n".
		"220\n". // Y value of extrusion direction
		"0.0\n".
		"230\n". // Z value of extrusion direction
		"1.0\n".
		"2\n". // Hatch pattern name
		$this->patternName."\n".
		"70\n". // Solid fill flag (solid fill = 1; pattern fill = 0); for MPolygon, the version of MPolygon
		"1\n".
		"71\n". // Associativity flag (associative = 1; non-associative = 0); for MPolygon, solid-fill flag (has solid fill = 1; lacks solid fill = 0)
		"1\n".
		"91\n". // Number of boundary paths
		"1\n".
		"92\n". // Boundary path type flag // 0 = Default; 1 = External; 2 = Polyline 4 = Derived; 8 = Textbox; 16 = Outermost
		"1\n";
		if($type=="line" || $type=="polyline"){
			$countPoints=count($points);
			if($type=="line"){
				$numPoints=$countPoints/2-1;
			}
			else{
				$numPoints=$countPoints/2;
			}
			$hatch.="93\n". // Number of polyline vertices
			$numPoints."\n";
			for ($i = 0; $i < ($countPoints-3); $i+=2){
				$x = $points[$i] + $this->offset[0];
				$y = $points[$i+1] + $this->offset[1];
				$x1=$points[$i+2] + $this->offset[0];
				$y1 = $points[$i+3] + $this->offset[1];
				$hatch .="72\n". // Edge type (only if boundary is not a polyline): 1 = Line; 2 = Circular arc; 3 = Elliptic arc; 4 = Spline
				$typeNumber."\n".
				"10\n" . // x Start point
				"{$x}\n" .
				"20\n" . // y Start point
				"{$y}\n" .
				"11\n" . // x Endpoint
				"{$x1}\n" .
				"21\n" . // y Endpoint
				"{$y1}\n";
			}
			if($type=="polyline"){
				$hatch .="72\n". // Edge type (only if boundary is not a polyline): 1 = Line; 2 = Circular arc; 3 = Elliptic arc; 4 = Spline
				$typeNumber."\n".
				"10\n" . // x Start point
				"{$x1}\n" .
				"20\n" . // y Start point
				"{$y1}\n" .
				"11\n" . // x Endpoint
				"{$x1}\n" .
				"21\n" . // y Endpoint
				"{$y1}\n";
			}
		}
		elseif($type=="circular"){
			$hatch.="93\n".
			"1\n".
			"72\n".
			$typeNumber."\n".
			"10\n". // x Center point
			$x."\n".
			"20\n". // y Center point
			$y."\n".
			"40\n". // radius
			$points[0]."\n".
			"50\n". // start angle
			"0.0\n".
			"51\n". // end angle
			"360.0\n".
			"73\n". // counterclock-wise flag
			"1\n";
		}
		elseif($type=="elliptic"){
			$startAngle=$points[4]/PI()*180;
			$endAngle=$points[5]/PI()*180;
			$hatch.="93\n".
			"1\n".
			"72\n".
			$typeNumber."\n".
			"10\n". // x Center point
			$x."\n".
			"20\n".
			$y."\n".
			"11\n". // x value Endpoint of major axis relative to center point
			$points[0]."\n".
			"21\n". //  Y value of endpoint of major axis relative to center point
			$points[1]."\n".
			"40\n". // Length of minor axis (percentage of major axis length)
			$points[3]."\n".
			"50\n". // start angle
			$startAngle."\n".
			"51\n". // end angle
			$endAngle."\n".
			"73\n". // counterclock-wise flag
			"1\n";
		}
		$hatch .="97\n". // Number of source boundary objects
		"1\n".
		"330\n". // handle of entity to fill
		$parentHandle."\n".
		"75\n". // Hatch style: 0 = Hatch “odd parity” area (Normal style) 1 = Hatch outermost area only (Outer style) 2 = Hatch through entire area (Ignore style)
		"0\n".
		"76\n". // Hatch pattern type: 0 = User-defined; 1 = Predefined; 2 = Custom
		"1\n". // user-defined not implemented
		"98\n". // Number of seed points
		"1\n".
		"10\n". // x value of Seed point
		"0.0\n".
		"20\n". // Y value of seed point
		"0.0\n";
		$this->blocks[$this->blockName]["shapes"][] = $hatch;
	}
	public function addImageDef($imageDefHandle,$imageDefReactorHandle,$pathToPictureDxf,$widthInPix,$heightInPix,$paperWidthOnePix,$paperHeightOnePix){
		$imageDef="0\n".
		"IMAGEDEF\n".
		"5\n".
		$imageDefHandle."\n".
		"102\n".
		"{ACAD_REACTORS\n".
		"330\n". // handle of the ACAD_IMAGE_DICT dictionary
		$this->ACAD_IMAGE_DICT_HANDLE."\n".
		"330\n". // ref to imagedef reactor object
		$imageDefReactorHandle."\n".
		"102\n".
		"}\n".
		"330\n".
		$this->ACAD_IMAGE_DICT_HANDLE."\n".
		"100\n".
		"AcDbRasterImageDef\n".
		"90\n".
		"0\n".
		"1\n".
		$pathToPictureDxf."\n".
		"10\n".
		$widthInPix."\n".
		"20\n".
		$heightInPix."\n".
		"11\n".
		$paperWidthOnePix."\n".
		"21\n".
		$paperHeightOnePix."\n".
		"280\n".
		"1\n".
		"281\n".
		"2\n";
		array_push($this->objects,$imageDef);
		return $this;
	}
	public function addImageDefReactor($imageHandle,$reactorHandle){
		$imDefReact="0\n".
		"IMAGEDEF_REACTOR\n".
		"5\n".
		$reactorHandle."\n".
		"330\n". // associated image entity handle
		$imageHandle."\n".
		"100\n".
		"AcDbRasterImageDefReactor\n".
		"90\n".
		"2\n".
		"330\n". // ID for associated image entity
		$imageHandle."\n";
		array_push($this->objects,$imDefReact);
		return $this;
	}
	// add image to current block or current layout
	// $x = x origin
	// $y = y origin
	// $z = z origin
	// $sizeX = horizontal size in mm
	// $sizeY = vertical size in mm
	// $pathToPicture = path to picture on server
	// $pathToPictureDxf = path to picture in the generated file
	public function addImage($x,$y,$z,$sizeX,$sizeY,$pathToPicture,$pathToPictureDxf){
		$size=getimagesize($pathToPicture);
		$widthInPix=$size[0];
		$heightInPix=$size[1];
		$paperWidthOnePix=1/($widthInPix/$sizeX); // convert size in mm to inches for horizontal resolution
		$paperHeightOnePix=1/($heightInPix/$sizeY); // convert size in mm to inches for vertical resolution
		$imageDefHandle=$this->getEntityHandle();
		$imageDefReactorHandle=$this->getEntityHandle();
		$handle=$this->getEntityHandle();
		$this->addImageDefReactor($handle,$imageDefReactorHandle);
		$this->addImageDef($imageDefHandle,$imageDefReactorHandle,$pathToPictureDxf,$widthInPix,$heightInPix,$paperWidthOnePix,$paperHeightOnePix);
		array_push($this->imageDict,"3\n".basename($pathToPicture)."\n350".$imageDefHandle."\n");
		$this->blocks[$this->blockName]["shapes"][]="0\n".
		"IMAGE\n".
		"5\n".
		$handle."\n".
		"330\n".
		$this->blocks[$this->blockName]["blockRecordhandle"]."\n".
		"100\n".
		"AcDbEntity\n".
		"67\n".
		"1\n".
		"8\n".
		$this->layerName."\n".
		"160\n".
		"140\n".
		"100\n".
		"AcDbRasterImage\n".
		"90\n".
		"0\n".
		"10\n".
		$x."\n". // x origin
		"20\n".
		$y."\n". // y origin
		"30\n". // z value
		$z."\n".
		"11\n". // U-vector of a single pixel (horizontal)
		$paperWidthOnePix."\n".
		"21\n".
		"0.0\n".
		"31\n".
		"0.0\n".
		"12\n".
		"0.0\n".
		"22\n". // Vertical-vector of a single pixel
		$paperHeightOnePix."\n".
		"32\n".
		"0.0\n".
		"13\n".
		$widthInPix."\n". // Image x size in pixels
		"23\n".
		$heightInPix."\n". // Image y size in pixels
		"340\n".
		$imageDefHandle."\n". // Hard reference to imagedef object
		"70\n". // Image display properties: 1 = Show image 2 = Show image when not aligned with screen 4 = Use clipping boundary 8 = Transparency is on
		"3\n".
		"280\n". //Clipping state: 0 = Off; 1 = On
		"0\n".
		"281\n". // Brightness value (0-100; default = 50)
		"50\n".
		"282\n". //Contrast value (0-100; default = 50)
		"50\n".
		"283\n". //Fade value (0-100; default = 0)
		"0\n".
		"360\n". // Hard reference to imagedef_reactor object
		$imageDefReactorHandle."\n".
		"71\n". // Clipping boundary type. 1 = Rectangular; 2 = Polygonal
		"1\n".
		"91\n". //Number of clip boundary vertices that follow
		"2\n".
		"14\n". // 1st Clip boundary vertex x
		"-0.5\n".
		"24\n". // 1st Clip boundary vertex y
		"-0.5\n".
		"14\n". // 2nd Clip boundary vertex x
		$sizeX."\n".
		"24\n". // 2nd Clip boundary vertex y
		$sizeY."\n".
		"290\n".
		"0\n";
		return $this;
	}
	public function addOle2Frame($x,$y,$z,$sizeX,$sizeY,$pathToPicture){ // to add binary picture inside a frame (no need to have picture file path)
		return $this; // function doesn't work. Doesn't know how autocad encodes image binary to hex.
		$x1=$x+$sizeX;
		$y1=$y+$sizeY;
		$handle=$this->getEntityHandle();
		$extType=explode(".",$pathToPicture);
		$extType=$pathToPicture[1];
		$im = new \Imagick(realpath($pathToPicture));
		$im->setResolution (100,100);
		$im->resizeImage($sizeX*0.0393701*100, $sizeY*0.0393701*100, false, 1, false);
		$imBlob=$im->getImageBlob();
		$binLength=strlen($imBlob);
		
		$imArray=str_split(bin2hex($imBlob), 64); // split binary data into 64 chars lines
		
      //  $imdata = base64_encode($im);
		//$imArray=str_split($imdata, 64); // split binary data into 64 char lines
		$binLines="";
		foreach($imArray as $line){
			$binLines.="310\n". // binary data
			$line."\n";
		}
		//$size=getimagesize($pathToPicture);
	//	$width=$size[0];
		//$height=$size[1];
		$this->blocks[$this->blockName]["shapes"][]="0\n".
		"OLE2FRAME\n".
		"5\n".
		$handle."\n".
		"330\n".
		$this->blocks[$this->blockName]["blockRecordhandle"]."\n".
		"100\n".
		"AcDbEntity\n".
		"67\n".
		"1\n".
		"8\n".
		$this->layerName."\n".
		"100\n".
		"AcDbOle2Frame\n".
		"70\n". // OLE version number
		"2\n".
		"3\n".
		"Picture (Device Independent Bitmap)\n".
		"10\n". // x value Upper-left corner
		$x."\n".
		"20\n". // y value Upper-left corner
		$y."\n".
		"30\n". // Z values of upper-left corner
		$z."\n".
		"11\n". // x Lower-right corner
		$x1."\n".
		"21\n". // y Lower-right corner
		$y1."\n".
		"31\n". // z Lower-right corner
		$z."\n".
		"71\n". // OLE object type, 1 = Link; 2 = Embedded; 3 = Static
		"3\n".
		"72\n". // Tile mode descriptor: 0 = Object resides in model space 1 = Object resides in paper space
		"1\n".
		"73\n". 
		"1\n".
		"90\n". // length of binary data
		$binLength."\n".
		$binLines.
		"1\n".
		"OLE\n".
		"1001\n".
		"ACAD\n".
		"1000\n".
		"OLEBEGIN\n".
		"1070\n".
		"70\n".
		"1070\n".
		"1\n".
		"1070\n".
		"71\n".
		"1070\n".
		"1\n".
		"1070\n".
		"40\n".
		"1040\n".
		"0.0\n".
		"1070\n".
		"41\n".
		"1040\n".
		"397.88\n".
		"1070\n".
		"42\n".
		"1040\n".
		"92.59\n".
		"1070\n".
		"72\n".
		"1070\n".
		"0\n".
		"1070\n".
		"3\n".
		"1000\n".
		"1070\n".
		"90\n".
		"1071\n".
		"12\n".
		"1070\n".
		"43\n".
		"1040\n".
		"4.23333\n".
		"1070\n".
		"4\n".
		"1000\n".
		"1070\n".
		"91\n".
		"1071\n".
		"12\n".
		"1070\n".
		"44\n".
		"1040\n".
		"4.23333\n".
		"1000\n".
		"OLEEND\n";
	}
	public function addViewport($handle){
		$this->blocks[$this->blockName]["shapes"][]="0\n".
		"VIEWPORT\n" .
		"5\n" .
		$handle."\n" .
		"330\n" .
		$this->blocks[$this->blockName]["blockRecordhandle"]."\n" .
		"100\n" .
		"AcDbEntity\n" .
		"67\n".
		"{VIEWPORT_ACTIVE}\n" .
		"8\n" .
		"0\n" .
		"100\n" .
		"AcDbViewport\n" .
		"10\n" .
		"0.0\n" .
		"20\n" .
		"0.0\n" .
		"30\n" .
		"0.0\n" .
		"40\n" .
		"853.84038\n" .
		"41\n" .
		"469.417268\n" .
		"68\n" .
		"1\n" .
		"69\n" .
		"1\n" .
		"12\n" .
		"255.691002\n" .
		"22\n" .
		"205.171049\n" .
		"13\n" .
		"0.0\n" .
		"23\n" .
		"0.0\n" .
		"14\n" .
		"10.0\n" .
		"24\n" .
		"10.0\n" .
		"15\n" .
		"10.0\n" .
		"25\n" .
		"10.0\n" .
		"16\n" .
		"0.0\n" .
		"26\n" .
		"0.0\n" .
		"36\n" .
		"1.0\n" .
		"17\n" .
		"0.0\n" .
		"27\n" .
		"0.0\n" .
		"37\n" .
		"0.0\n" .
		"42\n" .
		"50.0\n" .
		"43\n" .
		"0.0\n" .
		"44\n" .
		"0.0\n" .
		"45\n" .
		"469.417268\n" .
		"50\n" .
		"0.0\n" .
		"51\n" .
		"0.0\n" .
		"72\n" .
		"100\n" .
		"90\n" .
		"557168\n" .
		"1\n" .
		"\n".
		"281\n" .
		"0\n" .
		"71\n" .
		"1\n" .
		"74\n" .
		"0\n" .
		"110\n" .
		"0.0\n" .
		"120\n" .
		"0.0\n" .
		"130\n" .
		"0.0\n" .
		"111\n" .
		"1.0\n" .
		"121\n" .
		"0.0\n" .
		"131\n" .
		"0.0\n" .
		"112\n" .
		"0.0\n" .
		"122\n" .
		"1.0\n" .
		"132\n" .
		"0.0\n" .
		"79\n" .
		"0\n" .
		"146\n" .
		"0.0\n" .
		"170\n" .
		"0\n" .
		"61\n" .
		"5\n" .
		"292\n" .
		"1\n" .
		"282\n" .
		"1\n" .
		"141\n" .
		"0.0\n" .
		"142\n" .
		"0.0\n" .
		"63\n" .
		"256\n";
		return $this;
	}

    /**
     * Returns last error
     * @return null
     */
    public function getError()
    {
        return $this->error;
    }


    /**
     * Set offset
     * @param $x
     * @param $y
     * @param $z
     */
    public function setOffset($x, $y, $z = 0)
    {
        $this->offset = [$x, $y, $z];
    }


    /**
     * Get offset
     * @return array
     */
    public function getOffset()
    {
        return $this->offset;
    }


    /**
     * Save DXF document to file
     * @param string $fileName
     * @return bool True on success
     */
    function saveToFile($fileName)
    {
        $this->error = '';
        $dir = dirname($fileName);
        if (!is_dir($dir)) {
            $this->error = "Directory not exists: {$dir}";
            return false;
        }
        if (!file_put_contents($fileName, $this->getString())) {
            $this->error = "Error on save: {$fileName}";
            return false;
        }
        return true;
    }


    /**
     * Send DXF document to browser
     * @param string $fileName
     * @param bool $stop Set to FALSE if no need to exit from script
     */
    public function sendAsFile($fileName, $stop = true)
    {
        while (false !== ob_get_clean()) { };
        header("Content-Type: image/vnd.dxf");
        header("Content-Disposition: inline; filename={$fileName}");
        echo $this->getString();
        if ($stop) {
            exit;
        }
    }


    /**
     * Returns DXF document as string
     * @return string DXF document
     */
    private function getString()
    {
		if(count($this->layouts)==0){
			$dxf="no layout added";
			return $dxf;
		}
		$this->selectLayout($this->firstLayout); // make sure to select the first layout and the block linked to it because currently it doesn't load correctly otherwise.
		// must have something to do with the selected tab.
        $template = file_get_contents(__DIR__ . '/newTemplate.dxf');
        $lTypes = $this->getLtypesString();
        $layers = $this->getLayersString();
		$textStyles = $this->getTextStylesString();
        $entities = $this->getEntities();
		$blocks=$this->getBlocks();
		$block_record=$this->getBlockRecord();
		//file_put_contents("/tmp/rec",$block_record);
		$layoutDictionary=$this->getLayoutDictionary();
		$layouts=$this->getLayouts();
		$objects=implode('',$this->objects);
		$acadImageDictHandle=$this->ACAD_IMAGE_DICT_HANDLE;
		$imageNameAndPointer=implode("",$this->imageDict);
        $dxf = str_replace([
            '{LTYPES_TABLE}',
            '{LAYERS_TABLE}',
            '{ENTITIES_SECTION}',
			'{BLOCKS}',
			'{BLOCK_RECORD}',
			'{LAYOUT_DICTIONARY}',
			'{LAYOUT_LIST}',
			'{OTHER_OBJECTS}',
			'{ACAD_IMAGE_DICT_HANDLE}',
			'{IMAGE_NAME_AND_POINTER}',
			'{ACTIVE_LAYER}',
			'{HANDSEED}',
			'{LAST_ACTIVE_VIEWPORT}',
			'{LEFT_MARGIN}',
			'{RIGHT_MARGIN}',
			'{TOP_MARGIN}',
			'{BOTTOM_MARGIN}'
        ], [
            $lTypes,
            $layers,
            $entities,
			$blocks,
			$block_record,
			$layoutDictionary,
			$layouts,
			$objects,
			$acadImageDictHandle,
			$imageNameAndPointer,
			$this->layerName,
			$this->handleNumber,
			'',
			$this->leftMargin,
			$this->topMargin,
			$this->rightMargin,
			$this->bottomMargin
        ], $template);
		/*
		$dxf = str_replace([
            '{LTYPES_TABLE}',
            '{LAYERS_TABLE}',
            '{ENTITIES_SECTION}',
			'{BLOCKS}',
			'{BLOCK_RECORD}',
			'{LAYOUT_DICTIONARY}',
			'{LAYOUT_LIST}',
			'{OTHER_OBJECTS}',
			'{ACAD_IMAGE_DICT_HANDLE}',
			'{IMAGE_NAME_AND_POINTER}',
			'{ACTIVE_LAYER}',
			'{HANDSEED}',
			'{LAST_ACTIVE_VIEWPORT}',
			'{LEFT_MARGIN}',
			'{RIGHT_MARGIN}',
			'{TOP_MARGIN}',
			'{BOTTOM_MARGIN}'
        ], [
            $lTypes,
            $layers,
            '',
			'',
			'',
			'',
			'',
			'',
			$acadImageDictHandle,
			'',
			'',
			$this->handleNumber,
			'',
			$this->leftMargin,
			$this->topMargin,
			$this->rightMargin,
			$this->bottomMargin
        ], $template);
		*/
        return  $dxf;
    }
	
	private function getBlockRecord(){
		$block_record="";
		foreach($this->blocks as $block=>$blockArray){
			$blkrefs="";
			foreach($blockArray["blkrefs"] as $handle){
				$blkrefs.="331\n".
				$handle."\n";
			}
			if($blkrefs!=""){
				$blkrefs="102\n".
				"{BLKREFS\n" .
				$blkrefs .
				"102\n" .
				"}\n";
			}
			$block_record.="0\n".
			"BLOCK_RECORD\n" .
			  "5\n" .
			$blockArray["blockRecordhandle"]."\n" .
			"330\n" .
			$blockArray["ownerHandle"]."\n" .
			"100\n" .
			"AcDbSymbolTableRecord\n" .
			"100\n" .
			"AcDbBlockTableRecord\n" .
			"2\n" .
			"$block\n" .
			"340\n" .
			$blockArray["layoutHandle"]."\n" .
			$blkrefs .
			"70\n" .
			"4\n" .
			"280\n" .
			"1\n" .
			"281\n" .
			"1\n";
		}
		return $block_record;
	}
	private function getBlocks(){
		$blocks="";
		foreach($this->blocks as $block=>$blockArray){
			$blocks.="0\n" .
			"BLOCK\n" .
			"5\n" .
			$blockArray["handle"]."\n" .
			"330\n" .
			$blockArray["blockRecordhandle"]."\n" .
			"100\n" .
			"AcDbEntity\n" .
			"8\n" .
			"0\n" .
			"100\n" .
			"AcDbBlockBegin\n" .
			"2\n" .
			"$block\n" .
			"70\n" .
			"0\n" .
			"10\n" .
			"0.0\n" .
			"20\n" .
			"0.0\n" .
			"30\n" .
			"0.0\n" .
			"3\n" .
			"$block\n" .
			"1\n" .
			"$block\n"; 
			if($this->blockName!=$block || $blockArray["layoutHandle"]=="0"){ // include entities in the block section if layout is not the one selected or if block is not linked to a layout
				$blocks.=str_replace("{VIEWPORT_ACTIVE}","0",implode('', $blockArray["shapes"])); // useless to replace viewport_active if the block is not a layout, but simpler to include it for all blocks
			}
			$blocks.="0\n" .
			"ENDBLK\n" .
			"5\n" .
			$blockArray["handleEnd"]."\n" .
			"330\n" .
			$blockArray["blockRecordhandle"]."\n" .
			"100\n" .
			"AcDbEntity\n" .
			"8\n" .
			"0\n" .
			"100\n" .
			"AcDbBlockEnd\n";
		}
		return $blocks;
	}
	private function getLayouts(){
		$layouts="";
		foreach($this->layouts as $layout=>$layoutArray){
			$layouts.="0\n" .
			"LAYOUT\n" .
			"5\n" .
//			"24\n".
			$layoutArray["handle"]."\n" .
			"102\n" .
			"{ACAD_REACTORS\n" .
			"330\n" .
			"1D\n" . // handle of reactor dictionary
			"102\n" .
			"}\n" .
			"330\n" .
			"1D\n" .
			"100\n" .
			"AcDbPlotSettings\n" .
			"1\n" .
			"\n".
			"2\n" .
			"AutoCAD PDF (High Quality Print).pc3\n" .
			"4\n" .
			"ANSI_full_bleed_B_(17.00_x_11.00_Inches)\n" .
			"6\n" .
			"\n".
			"40\n" .
			$layoutArray["marginLeft"]."\n" .
			//"0\n".
			"41\n" .
			$layoutArray["marginBottom"]."\n" .
			//"0\n".
			"42\n" .
			$layoutArray["marginRight"]."\n" .
			//"0\n".
			"43\n" .
			$layoutArray["marginTop"]."\n" .
			//"0\n".
			"44\n" .
			$layoutArray["paperWidth"]."\n" .
			"45\n" .
			$layoutArray["paperHeight"]."\n" .
			"46\n" .
			"0.0\n" .
			//$layoutArray["marginLeft"]."\n" .
			"47\n" .
			"0.0\n" .
			//$layoutArray["marginBottom"]."\n" .
			"48\n" .
			"0.0\n" .
			"49\n" .
			"0.0\n" .
			"140\n" .
			"0.0\n" .
			"141\n" .
			"0.0\n" .
			"142\n" .
			"1.0\n" .
			"143\n" .
			"1.0\n" .
			"70\n" .
			"676\n" .
			"72\n" .
			"1\n" .
			"73\n" .
			$layoutArray["orientation"]."\n" .
			"74\n" .
			"1\n" . // drawing limits
			"7\n" .
			"DWF Virtual Pens.ctb\n".
			"75\n" . // scale of paper 0 = Scaled to Fit 1 = 1/128"=1'; 2 = 1/64"=1'; 3 = 1/32"=1' 4 = 1/16"=1'; 5 = 3/32"=1'; 6 = 1/8"=1' 7 = 3/16"=1'; 8 = 1/4"=1'; 9 = 3/8"=1' 10 = 1/2"=1'; 11 = 3/4"=1'; 12 = 1"=1' 13 = 3"=1'; 14 = 6"=1'; 15 = 1'=1' 16= 1:1 ; 17= 1:2; 18 = 1:4; 19 = 1:8; 20 = 1:10; 21= 1:16 22 = 1:20; 23 = 1:30; 24 = 1:40; 25 = 1:50; 26 = 1:100 27 = 2:1; 28 = 4:1; 29 = 8:1; 30 = 10:1; 31 = 100:1; 32 = 1000:1
			"0\n" . // means 1:1
			"76\n" .
			"0\n" .
			"77\n" .
			"2\n" .
			"78\n" .
			"300\n" .
			"147\n" .
			"1.0\n" .
			"148\n" .
			"0.0\n" .
			"149\n" .
			"0.0\n" .
			"100\n" .
			"AcDbLayout\n" .
			"1\n" .
			$layout."\n" .
			"70\n" .
			"0\n" .
			"71\n" . // this is the option for tab ordering
			$layoutArray["tab"]."\n" .
			"10\n" .
			"0.0\n" .
			"20\n" .
			"0.0\n" .
			"11\n" .
			"0.0\n" .
			"21\n" .
			"0.0\n" .
			"12\n" .
			"0.0\n" .
			"22\n" .
			"0.0\n" .
			"32\n" .
			"0.0\n" .
			"14\n" .
			"0.0\n" .
			"24\n" .
			"0.0\n" .
			"34\n" .
			"0.0\n" .
			"15\n" .
			"0.0\n" .
			"25\n" .
			"0.0\n" .
			"35\n" .
			"0.0\n" .
			"146\n" .
			"0.0\n" .
			"13\n" .
			"0.0\n" .
			"23\n" .
			"0.0\n" .
			"33\n" .
			"0.0\n" .
			"16\n" .
			"1.0\n" .
			"26\n" .
			"0.0\n" .
			"36\n" .
			"0.0\n" .
			"17\n" .
			"0.0\n" .
			"27\n" .
			"1.0\n" .
			"37\n" .
			"0.0\n" .
			"76\n" .
			"0\n" .
			"330\n" .
			$layoutArray["ownerHandle"]."\n" .
			"331\n" .
			$layoutArray["viewportHandle"]."\n".
			"1001\n".
			"ACAD_PSEXT\n".
			"1000\n".
			"None\n".
			"1000\n".
			"None\n".
			"1000\n".
			"Not applicable\n".
			"1000\n".
			"The layout will not be plotted unless a new plotter configuration name is selected.\n".
			"1070\n".
			"0\n";
		}
		return $layouts;
	}
	/*
	// generate list of layouts in the dictionary
	*/
	private function getLayoutDictionary(){
		$layout_dictionary="";
		 foreach ($this->layouts as $layout=>$layoutArray) {
			 $layout_dictionary.="3\n".
			 "$layout\n".
			 "350\n".
			 $layoutArray["handle"]."\n";
		 }
		return $layout_dictionary;
	}
	/*
	// get entities from active layout to insert them in the section entities
	*/
    private function getEntities(){
		// get entities from active layout
		return str_replace("{VIEWPORT_ACTIVE}","1",implode("",$this->blocks[$this->blockName]["shapes"]));
    }


    /**
     * Generates LTYPE items
     * @return string
     * @see http://help.autodesk.com/cloudhelp/2016/ENU/AutoCAD-DXF/files/GUID-F57A316C-94A2-416C-8280-191E34B182AC.htm
     * @see https://ezdxf.readthedocs.io/en/latest/dxfinternals/linetype_table.html
     */
    private function getLtypesString()
    {
        $ownerHandle = $this->getEntityHandle();
        $lTypes = "LTYPE\n5\n{$ownerHandle}\n330\n0\n100\nAcDbSymbolTable\n70\n4\n" .
			"0\n".
            "LTYPE\n5\n" . $this->getEntityHandle() . "\n330\n{$ownerHandle}\n100\nAcDbSymbolTableRecord\n100\nAcDbLinetypeTableRecord\n2\nByBlock\n70\n0\n3\n\n72\n65\n73\n0\n40\n0.0\n".
			"0\n".
            "LTYPE\n5\n" . $this->getEntityHandle() . "\n330\n{$ownerHandle}\n100\nAcDbSymbolTableRecord\n100\nAcDbLinetypeTableRecord\n2\nByLayer\n70\n0\n3\n\n72\n65\n73\n0\n40\n0.0\n";
        foreach ($this->lTypes as $type) {
            $number = $this->getEntityHandle();
            $name = isset(LineType::$lines[$type]) ? LineType::$lines[$type][0] : '';
            $pattern = isset(LineType::$lines[$type][1]) ? LineType::$lines[$type][1] : "73\n0\n40\n0.0";
            $lTypes .= "0\n".
				"LTYPE\n" .
                "5\n" . // Handle
                "{$number}\n" .
                "330\n" . // Soft-pointer ID/handle to owner object
                "{$ownerHandle}\n" .
                "100\n" . // Subclass marker (AcDbSymbolTable)
                "AcDbSymbolTableRecord\n" .
                "100\n" .
                "AcDbLinetypeTableRecord\n" .
                "2\n" . // Linetype name
                "{$type}\n" .
                "70\n" . // Standard flag values (bit-coded values)
                "64\n" .
                "3\n" . // Descriptive text for linetype
                "{$name}\n" .
                "72\n" . // Alignment code; value is always 65, the ASCII code for A
                "65\n" .
                "{$pattern}\n";
        }
        return $lTypes;
    }


    /**
     * Generates LAYERS
     * @return string
     * @see http://help.autodesk.com/cloudhelp/2016/ENU/AutoCAD-DXF/files/GUID-D94802B0-8BE8-4AC9-8054-17197688AFDB.htm
     */
    private function getLayersString()
    {
        $ownerNumber = $this->getEntityHandle();
        $layers = "LAYER\n5\n{$ownerNumber}\n330\n0\n100\nAcDbSymbolTable\n70\n1\n";
        if (count($this->layers) > 0) {
            foreach ($this->layers as $name => $layer) {
                $number = $this->getEntityHandle();
                $layers .= "0\n".
					"LAYER\n" .
                    "5\n" .
                    "{$number}\n" .
                    "330\n" .
                    "{$ownerNumber}\n" .
                    "100\n" . // Subclass marker
                    "AcDbSymbolTableRecord\n" .
                    "100\n" . // Subclass marker
                    "AcDbLayerTableRecord\n" .
                    "2\n" .
                    "{$name}\n" . // Layer name
                    "70\n" . // Standard flags (bit-coded values)
                    "64\n" .
                    "62\n" . // Color number (if negative, layer is off)
                    "{$layer['color']}\n" .
                    "6\n" . // Linetype name
                    "{$layer['lineType']}\n" .
					"370\n".
					$layer['lineWeight']."\n".
                    "390\n" .
                    "F\n";
            }
        }
        return $layers;
    }
	/**
     * Generates TEXTSTYLES
     * @return string
     * @see https://help.autodesk.com/cloudhelp/2016/ENU/AutoCAD-DXF/files/GUID-EF68AF7C-13EF-45A1-8175-ED6CE66C8FC9.htm
     */
    private function getTextStylesString()
    {
        $ownerNumber = $this->getEntityHandle();
        $textStyles = "STYLE\n5\n{$ownerNumber}\n330\n0\n100\nAcDbSymbolTable\n70\n3\n0\n";

        if (count($this->textStyles) > 0) {
            foreach ($this->textStyles as $name => $style) {
                $number = $this->getEntityHandle();
                $textStyles .= "STYLE\n" .
                    "5\n" .
                    "{$number}\n" .
                    "330\n" .
                    "{$ownerNumber}\n" .
                    "100\n" . // Subclass marker
                    "AcDbSymbolTableRecord\n" . // Subclass marker value
                    "100\n" . // Subclass marker group code
                    "AcDbTextStyleTableRecord\n" . // Subclass marker value
                    "2\n" . // Style name group code
                    "{$style['name']}\n" . // Style name value
                    "70\n" . // Standard flags group code
                    "{$style['stdFlags']}\n" . // Standard flags values
                    "40\n" . // Fixed text height group code
                    "{$style['fixedHeight']}\n" . // Fixed text height value;
                    "41\n" . // Width factor group code
                    "{$style['widthFactor']}\n" . // Width factor value
                    "50\n" . // Oblique angle group code
                    "{$style['obliqueAngle']}\n" . // Oblique angle value
                    "71\n" . // Text generation flags group code
                    "{$style['textGenerationFlags']}\n" . // Text generation flags value; 2 = Text is backward (mirrored in X); 4 = Text is upside down (mirrored in Y)
                    "42\n" . // Last height used group code
                    "{$style['lastHeightUsed']}\n" . // Last height used value
                    "3\n" . // Primary font file name group code
                    "{$style['font']}\n" . // Primary font file name value
                    "4\n" . // Bigfont file name group code
                    "{$style['bigFont']}\n"; // Bigfont file name value; blank if none
            }

            $textStyles .= "0\n";
        }
        return rtrim($textStyles, "\n");
    }

    public function __toString(){
        return $this->getString();
    }

}
