<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

<link rel="stylesheet" type="text/css"
	href="/pimcore/static/css/object_versions.css" />
<style type="text/css">
.prview_errortxt {
	color: red;
	font-family: "courier new";
	font-size: 12px;
	font-weight: normal;
}
</style>
</head>

<body>


<?php
if ($this->status != 'success') {
    $errorStatus = json_decode($this->status);
    echo "<p class='prview_errortxt'><b>Error(s):</b><br/>";
    if (count($errorStatus) > 0) {
        $errorStatus = array_unique($errorStatus);
        foreach ($errorStatus as $ekey => $error) {
            echo ++ $ekey . ") " . $error . "<br/>";
        }
    }else{
        echo "1) Object could not add/update on target environment <br/>";
    }
    
    echo "</p>";
}
$class = Object_Class::getByName($this->object->className);
$fields = $class->getFieldDefinitions();

?>

<table class="preview" border="0" cellpadding="0" cellspacing="0">
		<tr>
			<th>Name</th>
			<!--         <th>Key</th> -->
			<th>Value</th>
		</tr>
		<tr class="system">
			<td>Path</td>
			<!--         <td>o_path</td> -->
			<td><b><?php echo $this->objectFullpath; ?></b></td>
			<!--       <td><b><?php //echo $this->object->getFullpath(); ?></b></td> -->
		</tr>
		<tr class="system">
			<td>Type</td>
			<!--       <td>o_published</td> -->
			<td style="color: #067D9A"><b><?php echo trim(\Zend_Json::encode($this->object->className), '"'); if($this->object->o_type == 'variant'){ echo '- Variant';} ?></b></td>
		</tr>
		<tr class="system">
			<td>Date</td>
			<td><?php echo date('Y-m-d H:i:s', $this->object->modificationDate); ?></td>
		</tr>
		<tr class="system">
			<td>Published</td>
			<!--       <td>o_published</td> -->
			<td><?php

$isPublished = $this->object->published;
if ($isPublished) {
    echo "true";
} else {
    echo "false";
}

?></td>
		</tr>

		<tr class="">
			<td colspan="3">&nbsp;</td>
		</tr>

<?php $c = 0; ?>
<?php 
echo '<pre/>';
 
 foreach ($this->object->elements as $key => $value) {
 
    $definition =  $fields[$value->name];
   
?>

    <?php if($definition instanceof Pimcore\Model\Object\ClassDefinition\Data\Localizedfields) { ?>
        <?php foreach(\Pimcore\Tool::getValidLanguages() as $language) { ?>
            <?php foreach ($definition->getFieldDefinitions() as $lfd) { ?>
                <tr <?php if ($c % 2) { ?> class="odd" <?php } ?>>
			<td><?php echo $lfd->getTitle() ?> (<?php echo $language; ?>)</td>
			<!--  <td><?php echo $lfd->getName() ?></td> -->
			<td>
                        <?php
                if ($this->object->getValueForFieldName($fieldName)) {
                    echo $lfd->getVersionPreview($this->object->getValueForFieldName($fieldName)
                        ->getLocalizedValue($lfd->getName(), $language));
                }
                ?>
                    </td>
		</tr>
            <?php
                $c ++;
            }
            ?>
    <?php } ?>
    <?php } else if($definition instanceof Pimcore\Model\Object\ClassDefinition\Data\Objectbricks){?>
            <?php foreach($value->value as $asAllowedType) { ?>
                <?php
                
                $collectionDef = Pimcore\Model\Object\Objectbrick\Definition::getByKey($asAllowedType->type);
                
                foreach ($asAllowedType->value as $lfd) {
                    ?>
                    <?php
                    
                    $value1 = null;
                    $brickValue = $lfd->value;
                    if ($brickValue) {
                        $value1 = $brickValue;
                    }
                    ?>
                     <tr <?php if ($c % 2) { ?> class="odd" <?php } ?>>
			<td><?php echo ucfirst($asAllowedType->type) . " - " . $lfd->name ?> (<?php echo $language; ?>)</td>
			<td><?php
                    if ($lfd->type == "image" && ! empty($lfd->value)) {
                        
                        $imgTag = str_replace('{imageId}', $lfd->value, '<img src="/admin/asset/get-image-thumbnail/id/{imageId}/width/100/height/100/aspectratio/true" />');
                        echo $imgTag;
                    } else {
                        echo $value1 ;
                    }
                    
                    if ($lfd->type == "datetime" && $lfd->value != null) {
                        echo " (UTC)";
                    }
                    echo "<br/>";
                    
                    ?></td>
		</tr>
                    <?php
                    $c ++;
                }
                ?>
            <?php } ?>
   <?php } else if($definition instanceof Pimcore\Model\Object\ClassDefinition\Data\Fieldcollections){ ?>
            <?php foreach($value->value as $asAllowedType) { ?>
                <?php
                    
                    $collectionDef = Pimcore\Model\Object\Fieldcollection\Definition::getByKey($asAllowedType->type);
                    
                    foreach ($asAllowedType->value as $lfd) {
                        ?>
                    <?php
                        $value1 = null;
                        $fieldValue = $lfd->value;
                        
                        if ($fieldValue) {
                            $value1 = $fieldValue;
                        }
                        ?>
                     <tr <?php if ($c % 2) { ?> class="odd" <?php } ?>>
			<td><?php echo ucfirst($asAllowedType->type) . " - " . $lfd->name ?> (<?php echo $language; ?>)</td>
			<td><?php echo $value1; ?></td>
		</tr> 
                    <?php
                        $c ++;
                    }
                    ?><tr>
			<td colspan="2"></td>
		</tr>
            <?php } ?>
    <?php } else { ?>
        <tr <?php if ($c % 2) { ?> class="odd" <?php } ?>>
			<td><?php echo $definition->getTitle() ?></td>
			<td><?php
                if (is_array($value->value)) {
                    foreach ($value->value as $val) {
                        $object = Object_Abstract::getById($val->id);
                        if ($object) {
                            echo $object->getFullpath() . "<br/>";
                        }
                    }
                } else 
                    if ($value->type == "image" && ! empty($value->value)) {
                        
                        $imgTag = str_replace('{imageId}', $value->value, '<img src="/admin/asset/get-image-thumbnail/id/{imageId}/width/100/height/100/aspectratio/true" />');
                        echo $imgTag;
                    } else {
                        echo $value->value;
                        
                        if ($value->type == "datetime" &&  ! empty($value->value)) {
                            echo " (UTC)";
                        }
                        echo   "<br/>";
                    }
                ?></td>
		</tr>
    <?php } ?>
<?php

    $c ++;
}
?>
</table>


</body>
</html>