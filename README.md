
# Скачивание редакции Bitrix

- Скопировать в ~/bin/ или прописать в PATH (для Windows можно использовать из под командной строки Git Bash https://gitforwindows.org/)

- Запускать в рабочей директории проекта (куда нужно загрузить Bitrix)

## Минимизированное ядро

Часть модулей/компонентов убрана, при этом будет рабочая Bitrix панель и шаблоны.

Данная сборка после установки уместится в лимиты на количество файлов бесплатного хостинга Beget - https://beget.com/ru/free-hosting

`bx fetch core`

## "Микроядро"

Оставлены только классы модулей - чтобы пилить свой микрофреймворк, переиспользуя кодовую базу Bitrix.

`bx fetch micro`

### Cборка на основе полученного списка классов и файлов

В папке проекта должен существовать файл vendor/.deps.log - который содержит список полных названий классов, достаточный для запуска приложения.

Например:
```
Bitrix\Main\Application
Bitrix\Main\ArgumentException
Bitrix\Main\ArgumentNullException
Bitrix\Main\Config\Configuration
Bitrix\Main\Context
HelloWorld\App
HelloWorld\Config
HelloWorld\GreetingsService
```

Для сопоставления классов и файлов используются данные автозагрузки composer

```
composer -o dump-autoload
bitrix-copy-deps
```

Создать одно-файловое приложение
```
composer -o dump-autoload
bitrix-copy-deps onefile
```

## Стандартные редакции

`bx fetch` или `bx fetch start`

`bx fetch business`

`bx fetch crm`

## TODO

- пример для сборки классов
- замена ненужных и отсутсвующих классов/функций на заглушки
