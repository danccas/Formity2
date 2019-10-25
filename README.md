# Declarar Libreria

```php

Route::library('formity2.php');

```
# Declarar un formulario simple


```php

$form = Formity::getInstance('formulario_prueba');
$form->addField('fecha', 'input:date');
$form->addField('nombre', 'input:text');

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


