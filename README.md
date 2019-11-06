# Declarar Libreria

```php

require_once('formity2.php');

```
# Declarar un formulario simple


```php

$form = Formity::getInstance('formulario_prueba');
$form->addField('fecha', 'input:date');
$form->addField('nombre', 'input:text');

```
# Declarar campo no obligatorio

para declarar un campo obligatorio añadir "?",
por defecto es obligatorio un campo


```php
//campo obligatorio
$form->addField('fecha', 'input:date');

//campo no obligatorio
$form->addField('fecha?', 'input:date');

```




# Renderizar formulario

```php

$form = Formity::getInstance('formulario_prueba');
$form->renderInPage()


```

# Capturar Datos de un formulario

```php

    $formity2 = Formity::getInstance('formulario_prueba');
    
    if($form->byRequest()) {
      if(!$form->isValid($err)) {
        //hacer algo si hay error
      } else {
      	//capturando datos
        $data = $form->getData();

      }
    }

```

# Renderizar por separado un Formulario en una plantilla

```php 

<?php
$form=Formity::getInstance('nombre_formulario_a_renderizar');
echo $form->buildHeader();?>

<div>
  <div>
   <?php echo $form->getField('nombre_campo1')->render(); ?> 
  </div>
  <div>
   <?php echo $form->getField('nombre_campo2')->render(); ?> 
  </div>


</div>


<?php  echo $form->buildFooter()?>



```

# Campo autompletar
  declaracion
```php

  $form->addField('nombre_campo:nombre_campoamostrar', 'input:autocomplete')->setSize(6)->setOptions(function($form, $field, $q) use($db) {
  $q = str_replace('"', '', strtolower(trim($q)));
  $q = str_replace("'", '', $q);
  $rp = Libs::buscar_personal_en_empresa($db, Identify::g()->empresa_id, $q); //esta funcion debes cambiar segun lo que necesites
  if(!empty($rp)) {
    $rp = array_map(function($n) {
      //valores a mostrar en el label
      return array(
        'id'    => $n['id'],
        'label' => implode(' ', array($n['nombres'], $n['apellido_paterno'], $n['apellido_materno'])),
      );
    }, $rp);
  }
  return $rp;
});


```
el autocompletar al ser un campo con dos valores uno con el dato del id y el otro con el dato
que deseas mostrar , necesitaras la funcion para mostrar lo que se debe ver
```php
 
$form->getField('contacto_id')->setLabel("texto a mostrar");


```



