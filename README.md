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



