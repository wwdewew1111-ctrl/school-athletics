<?php
declare(strict_types=1);

/** Add attributes only to recognized, nonempty existing values; never change stored content. */
function sveden_mark(DOMElement $node, string $property): void
{
    $properties = preg_split('/\s+/', trim($node->getAttribute('itemprop'))) ?: [];
    if (!in_array($property, $properties, true)) $properties[] = $property;
    $node->setAttribute('itemprop', trim(implode(' ', $properties)));
}

function sveden_next_element(DOMNode $node): ?DOMElement
{
    $node = $node->nextSibling;
    while ($node && !($node instanceof DOMElement)) $node = $node->nextSibling;
    return $node instanceof DOMElement ? $node : null;
}

function sveden_add_markup(string $slug, string $html): string
{
    if ($html === '' || !in_array($slug, ['main', 'structure', 'documents', 'education', 'education-standards', 'management', 'teaching-staff', 'facilities', 'scholarships', 'paid', 'finance', 'international-cooperation', 'catering'], true) || !class_exists('DOMDocument')) return $html;
    // libxml's HTML4 parser cannot safely round-trip these modern interactive elements.
    if (preg_match('~<(?:script|video|audio|picture|source|svg|math|template|details|dialog|form|input|button)\b~i', $html)) return $html;
    $dom = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    try {
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8"><div id="sveden-markup-root">'.$html.'</div>', LIBXML_NONET | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        if (!$loaded) return $html;
        $xpath = new DOMXPath($dom);
        $root = $xpath->query('//*[@id="sveden-markup-root"]')->item(0);
        if (!$root instanceof DOMElement) return $html;

        if ($slug === 'main') {
            $fields = [
                'Полное наименование образовательной организации' => 'fullName',
                'Сокращенное наименование образовательной организации' => 'shortName',
                'Адрес местонахождения образовательной организации' => 'address',
                'Контактные телефоны образовательной организации' => 'telephone',
                'Адрес электронной почты образовательной организации' => 'email',
                'Дата создания образовательной организации' => 'regDate',
                'Режим работы образовательной организации' => 'workTime',
                'График работы образовательной организации' => 'workTime',
            ];
            foreach ($xpath->query('.//dt', $root) as $label) {
                $key = rtrim(trim($label->textContent), "* \t\n\r\0\x0B");
                $value = $label->nextSibling;
                while ($value && !($value instanceof DOMElement)) $value = $value->nextSibling;
                if (!$value instanceof DOMElement || $value->tagName !== 'dd' || trim($value->textContent) === '') continue;
                if (isset($fields[$key])) sveden_mark($value, $fields[$key]);
                if ($key === 'Учредители образовательной организации') {
                    sveden_mark($value, 'uchredLaw');
                    $first = $value->firstChild;
                    if ($first instanceof DOMText && trim($first->textContent) !== '') {
                        $span = $dom->createElement('span');
                        $span->appendChild($first->cloneNode(true));
                        $value->replaceChild($span, $first);
                        sveden_mark($span, 'nameUchred');
                    }
                    foreach ($xpath->query('.//a[@href]', $value) as $link) {
                        $href = $link->getAttribute('href');
                        if (str_starts_with($href, 'tel:')) sveden_mark($link, 'telUchred');
                        elseif (str_starts_with($href, 'mailto:')) sveden_mark($link, 'mailUchred');
                        elseif (preg_match('~^https?://~i', $href)) sveden_mark($link, 'websiteUchred');
                    }
                    foreach (iterator_to_array($value->childNodes) as $child) {
                        if ($child instanceof DOMText && preg_match('/^\s*Адрес:\s*(.+)$/u', $child->textContent, $m)) {
                            $span = $dom->createElement('span');
                            $span->appendChild($child->cloneNode(true));
                            $value->replaceChild($span, $child);
                            sveden_mark($span, 'addressUchred');
                        }
                    }
                }
            }
            foreach ($xpath->query('.//a[contains(@href,"license-")]', $root) as $link) sveden_mark($link, 'licenseDocLink');
        }

        if ($slug === 'documents') {
            $headings = [
                'Устав образовательной организации' => 'ustavDocLink',
                'Правила внутреннего распорядка обучающихся (воспитанников)' => 'localActStud',
                'Правила внутреннего трудового распорядка' => 'localActOrder',
                'Коллективный договор' => 'localActCollec',
                'Отчет о результатах самообследования' => 'reportEduDocLink',
                'Предписания органов, осуществляющих государственный контроль (надзор) в сфере образования, отчеты об исполнении таких предписаний (при наличии)' => 'prescriptionDocLink',
                'Правила приема обучающихся (воспитанников)' => 'priemDocLink',
                'Режим занятий обучающихся (воспитанников)' => 'modeDocLink',
                'Формы, периодичность и порядок текущего контроля успеваемости и промежуточной аттестации обучающихся (воспитанников)' => 'tekKontrolDocLink',
                'Порядок и основания перевода, отчисления и восстановления обучающихся (воспитанников)' => 'perevodDocLink',
                'Порядок оформления возникновения, приостановления и прекращения отношений между образовательной организацией и обучающимися (воспитанниками) и (или) родителями (законными представителями) несовершеннолетних обучающихся' => 'vozDocLink',
            ];
            foreach ($xpath->query('.//h3|.//h4', $root) as $heading) {
                $key = trim($heading->textContent);
                if (!isset($headings[$key])) continue;
                for ($value = sveden_next_element($heading); $value && !preg_match('/^h[1-6]$/', $value->tagName); $value = sveden_next_element($value)) {
                    $links = $xpath->query('.//a[@href]', $value);
                    if ($links->length) {
                        foreach ($links as $link) sveden_mark($link, $headings[$key]);
                    } elseif (trim($value->textContent) !== '') sveden_mark($value, $headings[$key]);
                }
            }
        }

        if ($slug === 'management') {
            foreach ($xpath->query('.//section[@aria-label]', $root) as $section) {
                $kind = $section->getAttribute('aria-label');
                if ($kind !== 'Директор' && $kind !== 'Заместитель директора') continue;
                sveden_mark($section, $kind === 'Директор' ? 'rucovodstvo' : 'rucovodstvoZam');
                $name = $xpath->query('.//h3', $section)->item(0);
                if ($name instanceof DOMElement && trim($name->textContent) !== '') {
                    sveden_mark($name, 'fio');
                    $position = $name->previousSibling;
                    while ($position && !($position instanceof DOMElement)) $position = $position->previousSibling;
                    if ($position instanceof DOMElement && trim($position->textContent) !== '') sveden_mark($position, 'post');
                }
                foreach ($xpath->query('.//a[@href]', $section) as $link) {
                    $href = $link->getAttribute('href');
                    if (str_starts_with($href, 'tel:')) sveden_mark($link, 'telephone');
                    elseif (str_starts_with($href, 'mailto:')) sveden_mark($link, 'email');
                }
            }
        }

        if ($slug === 'teaching-staff') {
            $headers = [
                'Фамилия тренера' => 'fio', 'Должность' => 'post',
                'Преподаваемые дисциплины' => 'teachingDiscipline',
                'Уровень профессинального образования' => 'teachingLevel',
                'Уровень профессионального образования' => 'teachingLevel',
                'Ученая степень' => 'degree', 'Ученое звание' => 'academStat',
                'Сведения о повышении квалификации' => 'qualification',
                'Сведения о профессиональной переподготовке' => 'profDevelopment',
                'Сведения о продолжительности опыта работы в профессиональной сфере' => 'specExperience',
            ];
            foreach ($xpath->query('.//table', $root) as $table) {
                $columns = [];
                foreach ($xpath->query('./thead/tr[1]/th', $table) as $i => $header) {
                    $label = trim($header->textContent);
                    if (isset($headers[$label])) $columns[$i] = $headers[$label];
                }
                if (!in_array('fio', $columns, true) || !in_array('post', $columns, true)) continue;
                foreach ($xpath->query('./tbody/tr', $table) as $row) {
                    $cells = $xpath->query('./td', $row);
                    if ($cells->length === 0) continue;
                    sveden_mark($row, 'teachingStaff');
                    foreach ($columns as $i => $property) {
                        $cell = $cells->item($i);
                        if ($cell instanceof DOMElement && trim($cell->textContent) !== '') sveden_mark($cell, $property);
                    }
                }
            }
        }

        if ($slug === 'scholarships') {
            $fields = [
                'Наличие и условия предоставления обучающимся стипендий' => 'grant',
                'Наличие и условия предоставления обучающимся мер социальной поддержки' => 'support',
            ];
            foreach ($xpath->query('.//h3', $root) as $heading) {
                $key = trim($heading->textContent);
                $value = sveden_next_element($heading);
                if (isset($fields[$key]) && $value instanceof DOMElement && trim($value->textContent) !== '') sveden_mark($value, $fields[$key]);
            }
        }

        if ($slug === 'education-standards') {
            foreach ($xpath->query('.//li[a]', $root) as $item) {
                if (trim($item->textContent) === 'Документы ФГОС ОО (перейти)') {
                    foreach ($xpath->query('.//a[@href]', $item) as $link) sveden_mark($link, 'eduFedDoc');
                }
            }
        }

        if ($slug === 'finance') {
            foreach ($xpath->query('.//h3', $root) as $heading) {
                if (trim($heading->textContent) !== 'План финансово-хозяйственной деятельности образовательной организации') continue;
                for ($value = sveden_next_element($heading); $value && !preg_match('/^h[1-6]$/', $value->tagName); $value = sveden_next_element($value)) {
                    foreach ($xpath->query('.//a[@href]', $value) as $link) sveden_mark($link, 'finPlanDocLink');
                }
            }
        }

        if ($slug === 'facilities') {
            foreach ($xpath->query('.//p', $root) as $paragraph) {
                if (trim($paragraph->textContent) === 'Доступ к информационным системам и информационно-телекоммуникационным сетям, в том числе приспособленным для использования инвалидами и лицами с ограниченными возможностями здоровья предоставляется сотрудникам в методическом кабинете.') sveden_mark($paragraph, 'comNetOvz');
            }
            $fields = [
                'Доступ к информационным системам и информационно-телекоммуникационным сетям' => 'comNet',
                'Электронные образовательные ресурсы, к которым обеспечивается доступ обучающихся' => 'erList',
                'Обеспечение доступа в здания образовательной организации, в том числе в общежитие, интернат, приспособленных для использования инвалидами и лицами с ограниченными возможностями здоровья' => 'ovz',
                'Наличие специальных технических средств обучения коллективного и индивидуального пользования инвалидов и лиц с ограниченными возможностями здоровья' => 'techOvz',
            ];
            foreach ($xpath->query('.//h3|.//h4', $root) as $heading) {
                $key = trim($heading->textContent);
                if (!isset($fields[$key])) continue;
                for ($value = sveden_next_element($heading); $value && !preg_match('/^h[1-6]$/', $value->tagName); $value = sveden_next_element($value)) {
                    if (trim($value->textContent) !== '') sveden_mark($value, $fields[$key]);
                }
            }
        }

        if ($slug === 'catering') {
            foreach ($xpath->query('.//p', $root) as $paragraph) {
                $text = trim($paragraph->textContent);
                if ($text === '1. Питание для обучающихся не организуется.') sveden_mark($paragraph, 'meals');
                elseif (str_starts_with($text, 'Охрана здоровья в МБУ ДО СШОР-4 обеспечивается')) {
                    for ($value = $paragraph; $value && $value->tagName === 'p'; $value = sveden_next_element($value)) {
                        if (trim($value->textContent) !== '') sveden_mark($value, 'health');
                    }
                    break;
                }
            }
        }

        if ($slug === 'structure') {
            foreach ($xpath->query('.//article[h4 and dl]', $root) as $article) {
                $name = $xpath->query('./h4', $article)->item(0);
                if (!$name instanceof DOMElement || trim($name->textContent) === '') continue;
                sveden_mark($article, 'structOrgUprav');
                sveden_mark($name, 'name');
                foreach ($xpath->query('.//dt', $article) as $label) {
                    $map = ['ФИО руководителя органа управления' => 'fio', 'Должность' => 'post', 'E-mail' => 'email'];
                    $key = trim($label->textContent);
                    $value = sveden_next_element($label);
                    if (isset($map[$key]) && $value instanceof DOMElement && $value->tagName === 'dd' && trim($value->textContent) !== '') sveden_mark($value, $map[$key]);
                }
                foreach ($xpath->query('.//p/a[@href]', $article) as $link) sveden_mark($link, 'divisionClauseDocLink');
            }
        }

        if ($slug === 'education') {
            foreach ($xpath->query('.//h3', $root) as $heading) {
                $title = trim($heading->textContent);
                $program = sveden_next_element($heading);
                if (!str_starts_with($title, 'Дополнительная образовательная программа спортивной подготовки') || !$program instanceof DOMElement || $xpath->query('.//a[@href]', $program)->length === 0) continue;
                $end = $heading;
                while ($end && !($end instanceof DOMElement && $end !== $heading && $end->tagName === 'h3')) $end = $end->nextSibling;
                $container = $dom->createElement('div');
                $heading->parentNode->insertBefore($container, $heading);
                for ($node = $heading; $node && $node !== $end;) {
                    $next = $node->nextSibling;
                    $container->appendChild($node);
                    $node = $next;
                }
                sveden_mark($container, 'eduOp');
                sveden_mark($heading, 'eduProf');
                $program = sveden_next_element($heading);
                if ($program instanceof DOMElement) foreach ($xpath->query('.//a[@href]', $program) as $link) sveden_mark($link, 'opMain');
                foreach ($xpath->query('.//h4', $container) as $subheading) {
                    if (trim($subheading->textContent) !== 'Учебный план') continue;
                    $plan = sveden_next_element($subheading);
                    if ($plan instanceof DOMElement) foreach ($xpath->query('.//a[@href]', $plan) as $link) sveden_mark($link, 'educationPlan');
                }
            }
            foreach ($xpath->query('.//h4', $root) as $heading) {
                $key = trim($heading->textContent);
                if ($key === 'Язык обучения') {
                    $value = sveden_next_element($heading);
                    if ($value instanceof DOMElement) {
                        foreach ($xpath->query('.//a[@href]', $value) as $link) sveden_mark($link, 'languageEl');
                    }
                }
            }
        }

        if ($slug === 'education') {
            $educationFields = [
                'Образовательная программа' => 'eduProf',
                'Уровень образования' => 'eduLevel',
                'Форма обучения' => 'eduForm',
                'Срок получения образования' => 'learningTerm',
                'Срок реализации' => 'learningTerm',
                'Учебные предметы' => 'eduPred',
                'Практики' => 'eduPrac',
            ];
            foreach ($xpath->query('.//p', $root) as $paragraph) {
                $text = trim($paragraph->textContent);
                foreach ($educationFields as $label => $property) {
                    if (str_contains($text, $label.':') || str_starts_with($text, $label)) sveden_mark($paragraph, $property);
                }
            }
            foreach ($xpath->query('.//a[@href]', $root) as $link) {
                $text = trim($link->textContent);
                if (preg_match('/численност|при[её]м|перевод|отчислен|восстановлен/ui', $text)) {
                    if (preg_match('/численност/ui', $text)) sveden_mark($link, 'eduChislenEl');
                    elseif (preg_match('/при[её]м/ui', $text)) sveden_mark($link, 'eduPriemEl');
                    else sveden_mark($link, 'eduPerevodEl');
                }
            }
        }

        if ($slug === 'education-standards') {
            foreach ($xpath->query('.//*[self::p or self::li or self::dd or self::a]') as $node) {
                if (trim($node->textContent) !== '') sveden_mark($node, 'eduFedDoc');
            }
        }

        if ($slug === 'facilities') {
            foreach ($xpath->query('.//h3|.//h4') as $heading) {
                $value = sveden_next_element($heading);
                if (!$value instanceof DOMElement || trim($value->textContent) === '') continue;
                $key = trim($heading->textContent);
                if (str_contains($key, 'информационным системам')) sveden_mark($value, 'comNet');
                elseif (str_contains($key, 'Электронные образовательные ресурсы')) sveden_mark($value, 'erList');
                elseif (str_contains($key, 'доступа в здания')) sveden_mark($value, 'ovz');
                elseif (str_contains($key, 'специальных технических средств')) sveden_mark($value, 'techOvz');
            }
        }

        if ($slug === 'paid') {
            $fields = [
                'Документ об установлении размера платы, взимаемой с родителей (законных представителей)' => ['paidParents'],
            ];
            foreach ($xpath->query('.//h3', $root) as $heading) {
                $key = trim($heading->textContent);
                $value = sveden_next_element($heading);
                if ($key === 'Платные образовательные услуги' && $value instanceof DOMElement && trim($value->textContent) === 'Платные услуги не осуществляются') {
                    foreach (['paidEdu', 'paidDog', 'paidSt'] as $property) sveden_mark($value, $property);
                }
                if (isset($fields[$key]) && $value instanceof DOMElement && trim($value->textContent) !== '') {
                    foreach ($fields[$key] as $property) sveden_mark($value, $property);
                }
            }
        }

        if ($slug === 'scholarships') {
            foreach ($xpath->query('.//p', $root) as $paragraph) {
                if (trim($paragraph->textContent) !== '') sveden_mark($paragraph, 'grant');
            }
        }

        if ($slug === 'international-cooperation') {
            foreach ($xpath->query('.//h3', $root) as $heading) {
                $value = sveden_next_element($heading);
                if ($value instanceof DOMElement && trim($value->textContent) !== '' && str_contains(trim($heading->textContent), 'договор')) {
                    sveden_mark($value, 'internationalDog');
                }
            }
        }

        if ($slug === 'catering') {
            foreach ($xpath->query('.//p', $root) as $paragraph) {
                $text = trim($paragraph->textContent);
                if ($text === '1. Питание для обучающихся не организуется.') sveden_mark($paragraph, 'meals');
                elseif (str_starts_with($text, 'Охрана здоровья в МБУ ДО СШОР-4 обеспечивается')) sveden_mark($paragraph, 'health');
            }
        }


        $result = '';
        foreach ($root->childNodes as $child) $result .= $dom->saveHTML($child);
        return $result;
    } catch (Throwable $e) {
        return $html;
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
}
