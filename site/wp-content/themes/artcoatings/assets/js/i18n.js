/**
 * ArtCoatings — 导航语言切换（changeLanguage）
 *
 * 站点是纯静态产物（Cloudflare Pages），没有后端，因此语言切换走客户端：
 *   1. 字典以「英文原文」为 key —— 英文就是源语言，切回 en 即还原原文；
 *   2. 只对**白名单选择器**内的元素做「整段文本精确匹配」后替换，
 *      绝不碰企业名 / 地址 / 产品描述等数据区，避免把品牌名误译
 *      （例如 Crown Paints 不应变成 Couronne Peintures）；
 *   3. 选择结果写入 localStorage，并同步 <html lang> 与 aria-label；
 *   4. 含 HTML（如 hero 的 <br>）的长文案走 data-i18n，key 同样是英文原文。
 *
 * 对外暴露 window.changeLanguage(code)，便于主题或外部脚本调用。
 */
(function () {
	'use strict';

	var STORAGE_KEY = 'ac_lang';
	var DEFAULT_LANG = 'en';

	// 语言代码 -> (显示名, html lang)
	var LANGS = {
		en: { label: 'English', html: 'en-US' },
		fr: { label: 'Français', html: 'fr-FR' },
		it: { label: 'Italiano', html: 'it-IT' },
		de: { label: 'Deutsch', html: 'de-DE' },
		pt: { label: 'Português', html: 'pt-PT' },
		el: { label: 'Ελληνικά', html: 'el-GR' }
	};

	// 字典：key = 英文原文（或 data-i18n 的 key），value = 目标语言译文
	var DICT = {
		fr: {
			// 导航 / 通用
			'Home': 'Accueil',
			'Companies': 'Entreprises',
			'News': 'Actualités',
			'About': 'À propos',
			'List your company': 'Référencer votre entreprise',
			'Skip to main content': 'Aller au contenu principal',
			'Language': 'Langue',
			// 首页
			'ART COATINGS DIRECTORY': 'ANNUAIRE ART COATINGS',
			'hero.title': 'Découvrez des entreprises<br>de revêtements artistiques',
			'hero.sub': 'Un annuaire indépendant basé en Sicile : revêtements décoratifs, enduits minéraux et à la chaux, microciment et systèmes métalliques — reliant marques, fabricants et prescripteurs en Italie et en Europe.',
			'Browse the directory': "Parcourir l'annuaire",
			'Industry news': 'Actualités du secteur',
			'Categories': 'Catégories',
			'Browse by category': 'Parcourir par catégorie',
			'All companies →': 'Toutes les entreprises →',
			'Featured companies': 'Entreprises en vedette',
			'View all →': 'Voir tout →',
			'More news →': "Plus d'actualités →",
			// 归档页
			'DIRECTORY': 'ANNUAIRE',
			'Company Directory': 'Annuaire des entreprises',
			'dir.sub': "Recherchez des entreprises de revêtements artistiques par catégorie et par région — trouvez rapidement les bonnes marques, les bons matériaux et les bons applicateurs.",
			'Category': 'Catégorie',
			'Region': 'Région',
			'All': 'Tous',
			'Previous': 'Précédent',
			'Next': 'Suivant',
			'No companies in this category yet.': 'Aucune entreprise dans cette catégorie pour le moment.',
			// 企业详情
			'Main products': 'Produits principaux',
			'Service regions': "Zones d'intervention",
			'Certifications': 'Certifications',
			'Unverified': 'Non vérifié',
			'Gallery': 'Galerie',
			'Contact information': 'Informations de contact',
			'Contact person': 'Personne à contacter',
			'Phone': 'Téléphone',
			'Address': 'Adresse',
			'Founded': 'Fondée en',
			'Website': 'Site web',
			'Profile': 'Profil',
			'Visit website ↗': 'Visiter le site ↗',
			'cert.note': "Basé sur des sources publiques et non confirmé de manière indépendante. Contactez l'entreprise pour vérifier.",
			// 页脚
			'Quick links': 'Liens rapides',
			'Contact': 'Contact',
			'Based in Sicily, Italy': 'Basé en Sicile, Italie',
			'footer.about': "Un annuaire indépendant des marques, fabricants et fournisseurs de revêtements artistiques — revêtements décoratifs, minéraux et à la chaux, microciment et systèmes métalliques en Italie et en Europe.",
			'footer.disclaimer': "ArtCoatings est un annuaire de référence indépendant. Les fiches sont informatives et ne contiennent que des éléments vérifiables publiquement ; nous ne sommes affiliés à aucune marque référencée, sauf mention explicite."
		},

		it: {
			'Home': 'Home',
			'Companies': 'Aziende',
			'News': 'Notizie',
			'About': 'Chi siamo',
			'List your company': 'Inserisci la tua azienda',
			'Skip to main content': 'Vai al contenuto principale',
			'Language': 'Lingua',
			'ART COATINGS DIRECTORY': 'DIRECTORY ART COATINGS',
			'hero.title': 'Scopri aziende affidabili<br>di rivestimenti artistici',
			'hero.sub': "Una directory indipendente con sede in Sicilia: rivestimenti decorativi, intonaci minerali e a calce, microcemento e sistemi metallici — che collega marchi, produttori e progettisti in Italia e in Europa.",
			'Browse the directory': 'Esplora la directory',
			'Industry news': 'Notizie dal settore',
			'Categories': 'Categorie',
			'Browse by category': 'Esplora per categoria',
			'All companies →': 'Tutte le aziende →',
			'Featured companies': 'Aziende in evidenza',
			'View all →': 'Vedi tutte →',
			'More news →': 'Più notizie →',
			'DIRECTORY': 'DIRECTORY',
			'Company Directory': 'Directory delle aziende',
			'dir.sub': 'Cerca aziende di rivestimenti artistici per categoria e regione — trova rapidamente i marchi, i materiali e gli applicatori giusti.',
			'Category': 'Categoria',
			'Region': 'Regione',
			'All': 'Tutte',
			'Previous': 'Precedente',
			'Next': 'Successivo',
			'No companies in this category yet.': 'Nessuna azienda in questa categoria.',
			'Main products': 'Prodotti principali',
			'Service regions': 'Aree di servizio',
			'Certifications': 'Certificazioni',
			'Unverified': 'Non verificato',
			'Gallery': 'Galleria',
			'Contact information': 'Informazioni di contatto',
			'Contact person': 'Referente',
			'Phone': 'Telefono',
			'Address': 'Indirizzo',
			'Founded': 'Fondata nel',
			'Website': 'Sito web',
			'Profile': 'Profilo',
			'Visit website ↗': 'Visita il sito ↗',
			'cert.note': 'Basato su fonti pubbliche e non confermato in modo indipendente. Contatta l\'azienda per verificare.',
			'Quick links': 'Link rapidi',
			'Contact': 'Contatti',
			'Based in Sicily, Italy': 'Con sede in Sicilia, Italia',
			'footer.about': 'Una directory indipendente di marchi, produttori e fornitori di rivestimenti artistici — decorativi, minerali e a calce, microcemento e sistemi metallici in Italia e in Europa.',
			'footer.disclaimer': 'ArtCoatings è una directory di riferimento indipendente. Le schede sono informative e contengono solo dati verificabili pubblicamente; non siamo affiliati ad alcun marchio salvo indicazione esplicita.'
		},

		de: {
			'Home': 'Startseite',
			'Companies': 'Unternehmen',
			'News': 'Nachrichten',
			'About': 'Über uns',
			'List your company': 'Unternehmen eintragen',
			'Skip to main content': 'Zum Hauptinhalt springen',
			'Language': 'Sprache',
			'ART COATINGS DIRECTORY': 'ART COATINGS VERZEICHNIS',
			'hero.title': 'Entdecken Sie vertrauenswürdige<br>Anbieter für künstlerische Beschichtungen',
			'hero.sub': 'Ein unabhängiges, auf Sizilien basierendes Verzeichnis für dekorative Beschichtungen, mineralische und Kalkputze, Mikrozement und Metallsysteme — es verbindet Marken, Hersteller und Planer in Italien und Europa.',
			'Browse the directory': 'Verzeichnis durchsuchen',
			'Industry news': 'Branchennews',
			'Categories': 'Kategorien',
			'Browse by category': 'Nach Kategorie stöbern',
			'All companies →': 'Alle Unternehmen →',
			'Featured companies': 'Ausgewählte Unternehmen',
			'View all →': 'Alle ansehen →',
			'More news →': 'Mehr News →',
			'DIRECTORY': 'VERZEICHNIS',
			'Company Directory': 'Unternehmensverzeichnis',
			'dir.sub': 'Suchen Sie Anbieter für künstlerische Beschichtungen nach Kategorie und Region — finden Sie schnell die passenden Marken, Materialien und Fachbetriebe.',
			'Category': 'Kategorie',
			'Region': 'Region',
			'All': 'Alle',
			'Previous': 'Zurück',
			'Next': 'Weiter',
			'No companies in this category yet.': 'Noch keine Unternehmen in dieser Kategorie.',
			'Main products': 'Hauptprodukte',
			'Service regions': 'Servicegebiete',
			'Certifications': 'Zertifizierungen',
			'Unverified': 'Unbestätigt',
			'Gallery': 'Galerie',
			'Contact information': 'Kontaktdaten',
			'Contact person': 'Ansprechpartner',
			'Phone': 'Telefon',
			'Address': 'Adresse',
			'Founded': 'Gegründet',
			'Website': 'Website',
			'Profile': 'Profil',
			'Visit website ↗': 'Website besuchen ↗',
			'cert.note': 'Basiert auf öffentlich zugänglichen Quellen und wurde nicht unabhängig bestätigt. Bitte kontaktieren Sie das Unternehmen zur Überprüfung.',
			'Quick links': 'Schnelllinks',
			'Contact': 'Kontakt',
			'Based in Sicily, Italy': 'Ansässig auf Sizilien, Italien',
			'footer.about': 'Ein unabhängiges Verzeichnis von Marken, Herstellern und Lieferanten für künstlerische Beschichtungen — dekorative, mineralische und Kalk-, Mikrozement- und Metallsysteme in Italien und Europa.',
			'footer.disclaimer': 'ArtCoatings ist ein unabhängiges Referenzverzeichnis. Die Einträge sind informativ und enthalten nur öffentlich überprüfbare Angaben; wir sind mit keiner gelisteten Marke verbunden, sofern nicht ausdrücklich angegeben.'
		},

		pt: {
			'Home': 'Início',
			'Companies': 'Empresas',
			'News': 'Notícias',
			'About': 'Sobre',
			'List your company': 'Cadastre sua empresa',
			'Skip to main content': 'Ir para o conteúdo principal',
			'Language': 'Idioma',
			'ART COATINGS DIRECTORY': 'DIRETÓRIO ART COATINGS',
			'hero.title': 'Descubra empresas confiáveis<br>de revestimentos artísticos',
			'hero.sub': 'Um diretório independente com sede na Sicília: revestimentos decorativos, argamassas minerais e de cal, microcimento e sistemas metálicos — conectando marcas, fabricantes e especificadores na Itália e na Europa.',
			'Browse the directory': 'Explorar o diretório',
			'Industry news': 'Notícias do setor',
			'Categories': 'Categorias',
			'Browse by category': 'Explorar por categoria',
			'All companies →': 'Todas as empresas →',
			'Featured companies': 'Empresas em destaque',
			'View all →': 'Ver todas →',
			'More news →': 'Mais notícias →',
			'DIRECTORY': 'DIRETÓRIO',
			'Company Directory': 'Diretório de empresas',
			'dir.sub': 'Pesquise empresas de revestimentos artísticos por categoria e região — encontre rapidamente as marcas, os materiais e os aplicadores certos.',
			'Category': 'Categoria',
			'Region': 'Região',
			'All': 'Todas',
			'Previous': 'Anterior',
			'Next': 'Próximo',
			'No companies in this category yet.': 'Nenhuma empresa nesta categoria ainda.',
			'Main products': 'Principais produtos',
			'Service regions': 'Regiões atendidas',
			'Certifications': 'Certificações',
			'Unverified': 'Não verificado',
			'Gallery': 'Galeria',
			'Contact information': 'Informações de contato',
			'Contact person': 'Pessoa de contato',
			'Phone': 'Telefone',
			'Address': 'Endereço',
			'Founded': 'Fundada em',
			'Website': 'Site',
			'Profile': 'Perfil',
			'Visit website ↗': 'Visitar site ↗',
			'cert.note': 'Baseado em fontes públicas e não confirmado de forma independente. Contate a empresa para verificar.',
			'Quick links': 'Links rápidos',
			'Contact': 'Contato',
			'Based in Sicily, Italy': 'Com sede na Sicília, Itália',
			'footer.about': 'Um diretório independente de marcas, fabricantes e fornecedores de revestimentos artísticos — decorativos, minerais e de cal, microcimento e sistemas metálicos na Itália e na Europa.',
			'footer.disclaimer': 'ArtCoatings é um diretório de referência independente. Os cadastros são informativos e incluem apenas dados publicamente verificáveis; não somos afiliados a nenhuma marca listada, salvo indicação explícita.'
		},

		el: {
			'Home': 'Αρχική',
			'Companies': 'Εταιρείες',
			'News': 'Νέα',
			'About': 'Σχετικά',
			'List your company': 'Καταχωρήστε την εταιρεία σας',
			'Skip to main content': 'Μετάβαση στο κύριο περιεχόμενο',
			'Language': 'Γλώσσα',
			'ART COATINGS DIRECTORY': 'ΚΑΤΑΛΟΓΟΣ ART COATINGS',
			'hero.title': 'Ανακαλύψτε αξιόπιστες εταιρείες<br>καλλιτεχνικών επιστρώσεων',
			'hero.sub': 'Ένας ανεξάρτητος κατάλογος με έδρα τη Σικελία: διακοσμητικές επιστρώσεις, ορυκτά και ασβεστοκονιάματα, μικροτσιμέντο και μεταλλικά συστήματα — συνδέει μάρκες, κατασκευαστές και μελετητές σε Ιταλία και Ευρώπη.',
			'Browse the directory': 'Περιήγηση στον κατάλογο',
			'Industry news': 'Νέα του κλάδου',
			'Categories': 'Κατηγορίες',
			'Browse by category': 'Περιήγηση ανά κατηγορία',
			'All companies →': 'Όλες οι εταιρείες →',
			'Featured companies': 'Προτεινόμενες εταιρείες',
			'View all →': 'Δείτε όλα →',
			'More news →': 'Περισσότερα νέα →',
			'DIRECTORY': 'ΚΑΤΑΛΟΓΟΣ',
			'Company Directory': 'Κατάλογος εταιρειών',
			'dir.sub': 'Αναζητήστε εταιρείες καλλιτεχνικών επιστρώσεων ανά κατηγορία και περιοχή — βρείτε γρήγορα τις κατάλληλες μάρκες, υλικά και συνεργεία.',
			'Category': 'Κατηγορία',
			'Region': 'Περιοχή',
			'All': 'Όλα',
			'Previous': 'Προηγούμενη',
			'Next': 'Επόμενη',
			'No companies in this category yet.': 'Δεν υπάρχουν εταιρείες σε αυτή την κατηγορία ακόμη.',
			'Main products': 'Κύρια προϊόντα',
			'Service regions': 'Περιοχές εξυπηρέτησης',
			'Certifications': 'Πιστοποιήσεις',
			'Unverified': 'Μη επιβεβαιωμένο',
			'Gallery': 'Γκαλερί',
			'Contact information': 'Στοιχεία επικοινωνίας',
			'Contact person': 'Υπεύθυνος επικοινωνίας',
			'Phone': 'Τηλέφωνο',
			'Address': 'Διεύθυνση',
			'Founded': 'Ίδρυση',
			'Website': 'Ιστότοπος',
			'Profile': 'Προφίλ',
			'Visit website ↗': 'Επίσκεψη ιστότοπου ↗',
			'cert.note': 'Βασίζεται σε δημόσιες πηγές και δεν έχει επιβεβαιωθεί ανεξάρτητα. Επικοινωνήστε με την εταιρεία για επαλήθευση.',
			'Quick links': 'Γρήγοροι σύνδεσμοι',
			'Contact': 'Επικοινωνία',
			'Based in Sicily, Italy': 'Με έδρα τη Σικελία, Ιταλία',
			'footer.about': 'Ένας ανεξάρτητος κατάλογος μαρκών, κατασκευαστών και προμηθευτών καλλιτεχνικών επιστρώσεων — διακοσμητικών, ορυκτών και ασβεστούχων, μικροτσιμέντου και μεταλλικών συστημάτων σε Ιταλία και Ευρώπη.',
			'footer.disclaimer': 'Το ArtCoatings είναι ένας ανεξάρτητος κατάλογος αναφοράς. Οι καταχωρήσεις είναι ενημερωτικές και περιέχουν μόνο δημόσια επαληθεύσιμα στοιχεία· δεν συνδεόμαστε με καμία καταχωρημένη μάρκα, εκτός αν αναφέρεται ρητά.'
		}
	};

	/**
	 * 允许翻译的元素白名单。
	 * 刻意**不包含** .card__title / .card__desc / .company__name / .prose /
	 * .panel__text —— 那些是企业数据或正文，翻译品牌名与地址会造成事实错误。
	 */
	var SELECTOR = [
		'.skip-link',
		'.nav-list a', '.nav-cta',
		'.lang-switch__current', '[data-i18n-label]',
		'.hero__eyebrow', '.hero__title', '.hero__sub',
		'.hero__actions .btn', '.hero__stats span',
		'.page-hero__eyebrow', '.page-hero__title', '.page-hero__sub',
		'.section__title', '.section__more',
		'.filter-label', '.pill--active',
		'.panel__title', '.badge-unverified', '.cert-note',
		'.contact-list > li > span',
		'.breadcrumb a',
		'.btn',
		'.empty', '.empty a',
		'.pagination a',
		'.footer-col h4', '.footer-col a', '.footer-brand p',
		'.footer-bottom span', '.footer-disclaimer',
		'[data-i18n]'
	].join(',');

	function norm(s) {
		return (s || '').replace(/\s+/g, ' ').trim();
	}

	/** 记住每个节点的英文原文，切回 en 时用它还原 */
	function remember(node) {
		if (node.dataset.acEn === undefined) {
			node.dataset.acEn = node.innerHTML;
		}
	}

	function applyNode(node, lang) {
		remember(node);

		// data-i18n 优先：长文案 / 含 HTML 的文案用语义 key
		var key = node.getAttribute('data-i18n');
		var base = key || norm(node.textContent);
		if (!base) {
			return;
		}

		if (lang === DEFAULT_LANG) {
			node.innerHTML = node.dataset.acEn;
			return;
		}

		var table = DICT[lang] || {};
		var out = table[base];
		if (!out) {
			// 退一步：data-i18n 节点也可能直接写了英文原文
			out = table[norm(node.textContent)];
		}
		if (out) {
			node.innerHTML = out;
		}
	}

	function applyAll(lang) {
		var nodes = document.querySelectorAll(SELECTOR);
		for (var i = 0; i < nodes.length; i++) {
			applyNode(nodes[i], lang);
		}
		document.documentElement.lang = (LANGS[lang] && LANGS[lang].html) || 'en-US';

		// 切换器自身的无障碍标签
		var btn = document.getElementById('langSwitchBtn');
		if (btn) {
			var t = DICT[lang] && DICT[lang]['Language'];
			btn.setAttribute('aria-label', t ? t : 'Language');
		}

		// 高亮当前语言
		var items = document.querySelectorAll('.lang-switch__menu button');
		for (var j = 0; j < items.length; j++) {
			var on = items[j].getAttribute('data-lang') === lang;
			items[j].setAttribute('aria-selected', on ? 'true' : 'false');
			items[j].classList.toggle('is-active', on);
		}

		var cur = document.querySelector('.lang-switch__current');
		if (cur && LANGS[lang]) {
			cur.textContent = LANGS[lang].label;
			// 当前语言名本身不参与翻译，清掉缓存避免二次切回时残留
			delete cur.dataset.acEn;
		}
	}

	/**
	 * 切换站点语言。
	 * @param {string} code  en | fr | it | de | pt | el
	 */
	function changeLanguage(code) {
		if (!LANGS[code]) {
			code = DEFAULT_LANG;
		}
		try {
			localStorage.setItem(STORAGE_KEY, code);
		} catch (e) { /* 隐私模式下忽略 */ }
		applyAll(code);
	}

	// ---------- 切换器交互 ----------
	function initSwitcher() {
		var wrap = document.getElementById('langSwitch');
		var btn = document.getElementById('langSwitchBtn');
		if (!wrap || !btn) {
			return;
		}

		btn.addEventListener('click', function (e) {
			e.stopPropagation();
			var open = wrap.classList.toggle('is-open');
			btn.setAttribute('aria-expanded', open ? 'true' : 'false');
		});

		wrap.addEventListener('click', function (e) {
			var b = e.target.closest ? e.target.closest('button[data-lang]') : null;
			if (!b) {
				return;
			}
			changeLanguage(b.getAttribute('data-lang'));
			wrap.classList.remove('is-open');
			btn.setAttribute('aria-expanded', 'false');
		});

		document.addEventListener('click', function (e) {
			if (!wrap.contains(e.target)) {
				wrap.classList.remove('is-open');
				btn.setAttribute('aria-expanded', 'false');
			}
		});

		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') {
				wrap.classList.remove('is-open');
				btn.setAttribute('aria-expanded', 'false');
			}
		});
	}

	// ---------- 启动 ----------
	function boot() {
		initSwitcher();
		var saved = DEFAULT_LANG;
		try {
			saved = localStorage.getItem(STORAGE_KEY) || DEFAULT_LANG;
		} catch (e) { /* noop */ }

		// 也支持 URL ?lang=fr，方便直接分享某语言
		var m = window.location.search.match(/[?&]lang=([a-z]{2})/i);
		if (m && LANGS[m[1].toLowerCase()]) {
			saved = m[1].toLowerCase();
		}

		if (!LANGS[saved]) {
			saved = DEFAULT_LANG;
		}
		applyAll(saved);
	}

	window.changeLanguage = changeLanguage;
	window.AC_LANGS = LANGS;

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
