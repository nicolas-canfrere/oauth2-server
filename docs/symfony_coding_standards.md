# Symfony Coding standards

## Structure

* Add a single space after each comma delimiter;

* Add a single space around binary operators (``==``, ``&&``, ...), with
  the exception of the concatenation (``.``) operator;

* Place unary operators (``!``, ``--``, ...) adjacent to the affected variable;

* Always use `identical comparison`_ unless you need type juggling;

* Use `Yoda conditions`_ when checking a variable against an expression to avoid
  an accidental assignment inside the condition statement (this applies to ``==``,
  ``!=``, ``===``, and ``!==``);

* Add a comma after each array item in a multi-line array, even after the
  last one;

* Add a blank line before ``return`` statements, unless the return is alone
  inside a statement-group (like an ``if`` statement);

* Use ``return null;`` when a function explicitly returns ``null`` values and
  use ``return;`` when the function returns ``void`` values;

* Do not add the ``void`` return type to methods in tests;

* Use braces to indicate control structure body regardless of the number of
  statements it contains;

* Define one class per file - this does not apply to private helper classes
  that are not intended to be instantiated from the outside and thus are not
  concerned by the `PSR-0`_ and `PSR-4`_ autoload standards;

* Declare the class inheritance and all the implemented interfaces on the same
  line as the class name;

* Declare class properties before methods;

* Declare public methods first, then protected ones and finally private ones.
  The exceptions to this rule are the class constructor and the ``setUp()`` and
  ``tearDown()`` methods of PHPUnit tests, which must always be the first methods
  to increase readability;

* Declare all the arguments on the same line as the method/function name, no
  matter how many arguments there are. The only exception are constructor methods
  using `constructor property promotion`_, where each parameter must be on a new
  line with `trailing comma`_;

* Use parentheses when instantiating classes regardless of the number of
  arguments the constructor has;

* Exception and error message strings must be concatenated using :phpfunction:`sprintf`;

* Exception and error messages must not contain backticks,
  even when referring to a technical element (such as a method or variable name).
  Double quotes must be used at all time:

  .. code-block:: diff

    - Expected `foo` option to be one of ...
    + Expected "foo" option to be one of ...

* Exception and error messages must start with a capital letter and finish with a dot ``.``;

* Exception, error and deprecation messages containing a class name must
  use ``get_debug_type()`` instead of ``::class`` to retrieve it:

  .. code-block:: diff

    - throw new \Exception(sprintf('Command "%s" failed.', $command::class));
    + throw new \Exception(sprintf('Command "%s" failed.', get_debug_type($command)));

* Do not use ``else``, ``elseif``, ``break`` after ``if`` and ``case`` conditions
  which return or throw something;

* Do not use spaces around ``[`` offset accessor and before ``]`` offset accessor;

* Add a ``use`` statement for every class that is not part of the global namespace;

* When PHPDoc tags like ``@param`` or ``@return`` include ``null`` and other
  types, always place ``null`` at the end of the list of types.

## Naming Conventions

* Use `camelCase`_ for PHP variables, function and method names, arguments
  (e.g. ``$acceptableContentTypes``, ``hasSession()``);

* Use `snake_case`_ for configuration parameters, route names and Twig template
  variables (e.g. ``framework.csrf_protection``, ``http_status_code``);

* Use SCREAMING_SNAKE_CASE for constants (e.g. ``InputArgument::IS_ARRAY``);

* Use `UpperCamelCase`_ for enumeration cases (e.g. ``InputArgumentMode::IsArray``);

* Use namespaces for all PHP classes, interfaces, traits and enums and
  `UpperCamelCase`_ for their names (e.g. ``ConsoleLogger``);

* Prefix all abstract classes with ``Abstract`` except PHPUnit ``*TestCase``.
  Please note some early Symfony classes do not follow this convention and
  have not been renamed for backward compatibility reasons. However, all new
  abstract classes must follow this naming convention;

* Suffix interfaces with ``Interface``;

* Suffix traits with ``Trait``;

* Don't use a dedicated suffix for classes or enumerations (e.g. like ``Class``
  or ``Enum``), except for the cases listed below.

* Suffix exceptions with ``Exception``;

* Prefix PHP attributes that relate to service configuration with ``As``
  (e.g. ``#[AsCommand]``, ``#[AsEventListener]``, etc.);

* Prefix PHP attributes that relate to controller arguments with ``Map``
  (e.g. ``#[MapEntity]``, ``#[MapCurrentUser]``, etc.);

* Use UpperCamelCase for naming PHP files (e.g. ``EnvVarProcessor.php``) and
  snake case for naming Twig templates and web assets (``section_layout.html.twig``,
  ``index.scss``);

* For type-hinting in PHPDocs and casting, use ``bool`` (instead of ``boolean``
  or ``Boolean``), ``int`` (instead of ``integer``), ``float`` (instead of
  ``double`` or ``real``);

* Don't forget to look at the more verbose :doc:`conventions` document for
  more subjective naming considerations.

.. _service-naming-conventions:

## Service Naming Conventions

* A service name must be the same as the fully qualified class name (FQCN) of
  its class (e.g. ``App\EventSubscriber\UserSubscriber``);

* If there are multiple services for the same class, use the FQCN for the main
  service and use lowercase and underscored names for the rest of services.
  Optionally divide them in groups separated with dots (e.g.
  ``something.service_name``, ``fos_user.something.service_name``);

* Use lowercase letters for parameter names (except when referring
  to environment variables with the ``%env(VARIABLE_NAME)%`` syntax);

* Add class aliases for public services (e.g. alias ``Symfony\Component\Something\ClassName``
  to ``something.service_name``).
