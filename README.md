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

# Declarar Campo con sub Campos
```php
$form = Formity::getInstance('formulario_prueba');
  $pform = Formity::getInstance('deuda');
  $pform->addField('id?', 'input:hidden');
  $pform->addField('fecha_vencimiento', 'input:date')->setValue(date('Y-m-d'));
  $pform->addField('monto', 'input:number')->setMax(99999);
$form->addField('deudas', $pform, '1-12');

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
      if($form->isValid($err)) {
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

#  Creando Sub_Formularios
declaracion
```php
  $form = Formity::getInstance('formualrio_prueba');

     $form_hijo = Formity::getInstance('formulario_hijo');
     $form_hijo->addField('campo', 'input:text');

  $form->addField('formulario_padre', $form_hijo, '1-12');
  
```

#validacion manual 
por defecto el formity2 tiene  la funcion isvalid() 
para validar campos pero para validaciones extras
se puede usar ->setError()
ejemplo:
```php
if($re->byRequest()) {
         if($re->isValid($err)) {
           if("hay un error") {
           $re->setError('Ingreso zonas iguales');
          }

         }

      } 

```


# Metodo onChange()
Esta funcion sirve para poder hacer un cambio desde un elemento a otro  elemento
del mismo formulario 
```php
//declarar primero el formulario con todos sus elementos
$form = Formity::getInstance('formulario_onchange');
$form->addField('campo1', 'input:text');
$form->addField('campo2','textarea');

//luego llamar al elemento que generara el onChange
$form->getField('nombre')->onChange(function($form , $field){
  //puedes capturar los datos del formulario si lo requieres
  $data = $form->getData();
  //d esta manera puedes mostrar en otro elemnto del formulario
  $form->getField('campo2')->setValue("ESTA PLACA ESTA REGISTRADA EN OTRA EMPRESA");
  return ;

});


```



