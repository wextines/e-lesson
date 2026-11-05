export type Lang = 'ru' | 'uz' | 'en';

export interface Course {
  id: string;
  name: string;
  grade: string;
  price: string;
  priceUnit: string;
  badge?: string;
  description: string;
}

export interface Testimonial {
  quote: string;
  author: string;
  meta: string;
  rating: number;
}

export interface WhyStory {
  num: string;
  label: string;
  title: string;
  text: string;
}

export interface Stat {
  value: string;
  label: string;
}

export interface Translation {
  nav: {
    about: string;
    courses: string;
    results: string;
    contacts: string;
    cta: string;
  };
  lang: { ru: string; uz: string; en: string };
  hero: {
    eyebrow: string;
    headline1: string;
    headline2: string;
    headlineHighlight?: string;
    text: string;
    btnPrimary: string;
    btnSecondary: string;
    stats: Stat[];
  };
  why: {
    eyebrow: string;
    title: string;
    text: string;
    stories: WhyStory[];
  };
  courses: {
    eyebrow: string;
    title: string;
    text: string;
    featured: string;
    perMonth: string;
    enroll: string;
    list: Course[];
  };
  results: {
    eyebrow: string;
    title: string;
    testimonials: Testimonial[];
    prev: string;
    next: string;
  };
  cta: {
    eyebrow: string;
    title: string;
    text: string;
    name: string;
    namePlaceholder: string;
    phone: string;
    phonePlaceholder: string;
    course: string;
    coursePlaceholder: string;
    selectCourse: string;
    button: string;
    submitting: string;
    success: string;
    successText: string;
    closeSuccess: string;
    errName: string;
    errPhone: string;
    errCourse: string;
  };
  footer: {
    tagline: string;
    phone: string;
    email: string;
    address: string;
    hours: string;
    nav: string;
    rights: string;
    madeWith: string;
  };
}

export const translations: Record<Lang, Translation> = {
  ru: {
    nav: {
      about: 'О центре',
      courses: 'Курсы',
      results: 'Результаты',
      contacts: 'Контакты',
      cta: 'Связаться',
    },
    lang: { ru: 'RU', uz: 'UZ', en: 'EN' },
    hero: {
      eyebrow: 'Образовательный центр · Ташкент',
      headline1: 'Знания, которые',
      headline2: 'открывают',
      headlineHighlight: 'возможности.',
      text: 'Помогаем детям развиваться, уверенно осваивать новые знания и достигать своих целей.',
      btnPrimary: 'Записаться на пробное занятие',
      btnSecondary: 'Смотреть курсы',
      stats: [
        { value: '500+', label: 'учеников' },
        { value: '15+', label: 'направлений' },
        { value: '98%', label: 'поступают' },
        { value: '5', label: 'лет развиваемся' },
      ],
    },
    why: {
      eyebrow: 'Почему выбирают нас',
      title: 'Учим понимать суть предмета, а не просто заучивать правила',
      text: 'Понятная программа, сильные преподаватели и поддержка на каждом этапе. Помогаем восполнить пробелы в знаниях, полюбить учёбу и уверенно достигать целей.',
      stories: [
        {
          num: '01',
          label: 'Наставники',
          title: 'Сильные преподаватели с опытом',
          text: 'Объясняют сложный материал простым языком, помогают разобраться в сути и поддерживают живой интерес к учёбе.',
        },
        {
          num: '02',
          label: 'Подход',
          title: 'Индивидуальная траектория',
          text: 'Определяем текущий уровень, закрываем пробелы и готовим к отличным оценкам, экзаменам и олимпиадам.',
        },
        {
          num: '03',
          label: 'Результат',
          title: 'Заметный прогресс и уверенность',
          text: 'Регулярная обратная связь для родителей, контроль успеваемости и видимый рост результатов ребёнка.',
        },
      ],
    },
    courses: {
      eyebrow: 'Направления',
      title: 'Курсы для каждой цели',
      text: 'От школьной программы до олимпиад и поступления — выберите путь, который подходит именно вам.',
      featured: 'Рекомендуем',
      perMonth: '/ мес',
      enroll: 'Записаться',
      list: [
        {
          id: 'math',
          name: 'Математика',
          grade: '5–6 класс',
          price: '450 000 сум',
          priceUnit: ' / мес',
          description: 'Логика, уверенность и прочная база для любых задач.',
        },
        {
          id: 'english',
          name: 'Английский язык',
          grade: '1–11 класс',
          price: '500 000 сум',
          priceUnit: ' / мес',
          badge: 'Популярный',
          description: 'Разговорная практика, грамматика и подготовка к IELTS.',
        },
        {
          id: 'physics',
          name: 'Физика',
          grade: '7–11 класс',
          price: '480 000 сум',
          priceUnit: ' / мес',
          description: 'Понимание процессов и решение задач повышенной сложности.',
        },
      ],
    },
    results: {
      eyebrow: 'Отзывы',
      title: 'Истории, которые вдохновляют',
      testimonials: [
        {
          quote: 'За год подтянул математику с тройки до пятёрки. Здесь занимаются именно тем, где были пробелы, поэтому результат почувствовался быстро.',
          author: 'Тимур Рахимов',
          meta: '9 класс · математика',
          rating: 5,
        },
        {
          quote: 'После занятий дочь перестала бояться отвечать у доски. Учитель поддерживает и объясняет так, что ей самой хочется разобраться.',
          author: 'Малика Саидова',
          meta: 'мама ученицы · английский',
          rating: 5,
        },
      ],
      prev: 'Предыдущий',
      next: 'Следующий',
    },
    cta: {
      eyebrow: 'Бесплатное пробное занятие',
      title: 'Первый шаг к успеху начинается здесь',
      text: 'Оставьте заявку — мы подберём программу, познакомим с преподавателем и ответим на вопросы.',
      name: 'Имя',
      namePlaceholder: 'Ваше имя',
      phone: 'Телефон (+998)',
      phonePlaceholder: '90 123 45 67',
      course: 'Выберите курс',
      coursePlaceholder: 'Выберите курс',
      selectCourse: 'Выберите курс',
      button: 'Записаться на пробное занятие',
      submitting: 'Отправляем…',
      success: 'Заявка отправлена',
      successText: 'Мы свяжемся с вами в течение дня, чтобы подобрать удобное время.',
      closeSuccess: 'Отправить ещё одну',
      errName: 'Пожалуйста, укажите имя',
      errPhone: 'Введите корректный номер телефона',
      errCourse: 'Выберите курс',
    },
    footer: {
      tagline: 'Помогаем детям учиться с интересом, уверенностью и заметным результатом.',
      phone: '+998 71 200 40 40',
      email: 'hello@sfera.uz',
      address: 'ул. Шахрисабз, 12, Ташкент',
      hours: 'Пн–Сб, 09:00–20:00',
      nav: 'Навигация',
      rights: 'Все права защищены',
      madeWith: 'Сделано с заботой в Ташкенте',
    },
  },

  uz: {
    nav: {
      about: 'Markaz haqida',
      courses: 'Kurslar',
      results: 'Natijalar',
      contacts: 'Kontaktlar',
      cta: 'Bog\'lanish',
    },
    lang: { ru: 'RU', uz: 'UZ', en: 'EN' },
    hero: {
      eyebrow: 'Ta\'lim markazi · Toshkent',
      headline1: 'Kelajak sari',
      headline2: 'ochiladigan',
      headlineHighlight: 'imkoniyatlar.',
      text: 'Bolalarga rivojlanishga, yangi bilimlarni ishonch bilan egallashga va o‘z maqsadlariga erishishga yordam beramiz.',
      btnPrimary: 'Sinov darsiga yozilish',
      btnSecondary: 'Kurslarni ko\'rish',
      stats: [
        { value: '500+', label: 'o\'quvchi' },
        { value: '15+', label: 'yo\'nalish' },
        { value: '98%', label: 'o\'qishga kiradi' },
        { value: '5', label: 'yil rivojlanamiz' },
      ],
    },
    why: {
      eyebrow: 'Nega aynan biz',
      title: 'Qoidalarni yodlatish emas, fanni chuqur tushunishni o‘rgatamiz',
      text: 'Tushunarli dastur, tajribali ustozlar va har bir bosqichda qo‘llab-quvvatlash. Bilimdagi bo‘shliqlarni to‘ldiramiz va maqsadlarga erishishga yordam beramiz.',
      stories: [
        {
          num: '01',
          label: 'Ustozlar',
          title: 'Katta tajribaga ega kuchli mutaxassislar',
          text: 'Murakkab mavzularni sodda tilda tushuntiradi, har bir o‘quvchiga yondashuv topadi va qiziqish uyg‘otadi.',
        },
        {
          num: '02',
          label: 'Yondashuv',
          title: 'Shaxsiy o‘quv rejasi',
          text: 'Boshlang‘ich darajani aniqlaymiz, bo‘shliqlarni to‘ldiramiz va yuqori baholar hamda imtihonlarga tayyorlaymiz.',
        },
        {
          num: '03',
          label: 'Natija',
          title: 'Sezilarli o‘sish va ishonch',
          text: 'Ota-onalar uchun muntazam hisobotlar, o‘zlashtirish nazorati va bolaning bilimiga bo‘lgan ishonchini oshirish.',
        },
      ],
    },
    courses: {
      eyebrow: 'Yo\'nalishlar',
      title: 'Har bir maqsad uchun kurslar',
      text: 'Maktab dasturidan olimpiada va kirish imtihonlarigacha — sizga mos yo\'lni tanlang.',
      featured: 'Tavsiya etamiz',
      perMonth: '/ oy',
      enroll: 'Yozilish',
      list: [
        {
          id: 'math',
          name: 'Matematika',
          grade: '5–6 sinf',
          price: '450 000 so\'m',
          priceUnit: ' / oy',
          description: 'Mantiq, ishonch va har qanday vazifa uchun mustahkam asos.',
        },
        {
          id: 'english',
          name: 'Ingliz tili',
          grade: '1–11 sinf',
          price: '500 000 so\'m',
          priceUnit: ' / oy',
          badge: 'Mashhur',
          description: 'So\'zlashuv amaliyoti, grammatika va IELTS ga tayyorgarlik.',
        },
        {
          id: 'physics',
          name: 'Fizika',
          grade: '7–11 sinf',
          price: '480 000 so\'m',
          priceUnit: ' / oy',
          description: 'Jarayonlarni tushunish va murakkab masalalarni yechish.',
        },
      ],
    },
    results: {
      eyebrow: 'Sharhlar',
      title: 'Ilhomlantiruvchi hikoyalar',
      testimonials: [
        {
          quote: 'Bir yilda matematikani uchlikdan beshlikka yetkazdim. Bu yerda aynan bo\'shliqlar bor joyda ishlaydilar, shuning uchun natija tez sezildi.',
          author: 'Timur Rahimov',
          meta: '9 sinf · matematika',
          rating: 5,
        },
        {
          quote: 'Darslardan keyin qizim doskada javob berishdan qo\'rqmay qoldi. O\'qituvchi qo\'llab-quvvatlaydi va shunday tushuntiradiki, uning o\'zi tushunishni xohlaydi.',
          author: 'Malika Saidova',
          meta: 'o\'quvchi onasi · ingliz tili',
          rating: 5,
        },
      ],
      prev: 'Oldingi',
      next: 'Keyingi',
    },
    cta: {
      eyebrow: 'Bepul sinov darsi',
      title: 'Muvaffaqiyatga birinchi qadam shu yerda boshlanadi',
      text: 'Ariza qoldiring — dasturni tanlaymiz, o\'qituvchi bilan tanishtiramiz va savollarga javob beramiz.',
      name: 'Ism',
      namePlaceholder: 'Ismingiz',
      phone: 'Telefon (+998)',
      phonePlaceholder: '90 123 45 67',
      course: 'Kursni tanlang',
      coursePlaceholder: 'Kursni tanlang',
      selectCourse: 'Kursni tanlang',
      button: 'Sinov darsiga yozilish',
      submitting: 'Yuborilmoqda…',
      success: 'Ariza yuborildi',
      successText: 'Kun davomida siz bilan bog\'lanib, qulay vaqtni tanlaymiz.',
      closeSuccess: 'Yana yuborish',
      errName: 'Iltimos, ismingizni kiriting',
      errPhone: 'To\'g\'ri telefon raqamini kiriting',
      errCourse: 'Kursni tanlang',
    },
    footer: {
      tagline: 'Bolalarga qiziqish, ishonch va sezilarli natija bilan o\'qishga yordam beramiz.',
      phone: '+998 71 200 40 40',
      email: 'hello@sfera.uz',
      address: 'Shahrisabz ko\'chasi, 12, Toshkent',
      hours: 'Du–Shan, 09:00–20:00',
      nav: 'Navigatsiya',
      rights: 'Barcha huquqlar himoyalangan',
      madeWith: 'Toshkentda g\'amxo\'rlik bilan yaratilgan',
    },
  },

  en: {
    nav: {
      about: 'About',
      courses: 'Courses',
      results: 'Results',
      contacts: 'Contacts',
      cta: 'Contact us',
    },
    lang: { ru: 'RU', uz: 'UZ', en: 'EN' },
    hero: {
      eyebrow: 'Educational center · Tashkent',
      headline1: 'Knowledge that',
      headline2: 'unlocks',
      headlineHighlight: 'possibilities.',
      text: 'We help children grow, master new skills with confidence, and achieve their goals.',
      btnPrimary: 'Book a trial lesson',
      btnSecondary: 'View courses',
      stats: [
        { value: '500+', label: 'students' },
        { value: '15+', label: 'programs' },
        { value: '98%', label: 'enter university' },
        { value: '5', label: 'years growing' },
      ],
    },
    why: {
      eyebrow: 'Why Choose Us',
      title: 'We teach deep understanding, not just rote memorization',
      text: 'A structured curriculum, experienced mentors, and steady guidance. We help students master fundamentals, enjoy learning, and achieve real results.',
      stories: [
        {
          num: '01',
          label: 'Mentors',
          title: 'Experienced and dedicated teachers',
          text: 'Explaining complex concepts clearly, finding the right approach for every student, and inspiring curiosity.',
        },
        {
          num: '02',
          label: 'Approach',
          title: 'Personalized learning trajectory',
          text: 'Diagnosing starting levels, addressing specific learning gaps, and preparing for top grades and exams.',
        },
        {
          num: '03',
          label: 'Results',
          title: 'Measurable progress and confidence',
          text: 'Regular feedback for parents, performance monitoring, and tangible academic growth.',
        },
      ],
    },
    courses: {
      eyebrow: 'Programs',
      title: 'Courses for every goal',
      text: 'From school curriculum to olympiads and admissions — choose the path that fits you.',
      featured: 'Recommended',
      perMonth: '/ mo',
      enroll: 'Enroll',
      list: [
        {
          id: 'math',
          name: 'Mathematics',
          grade: 'Grades 5–6',
          price: '450,000 UZS',
          priceUnit: ' / mo',
          description: 'Logic, confidence, and a solid foundation for any problem.',
        },
        {
          id: 'english',
          name: 'English Language',
          grade: 'Grades 1–11',
          price: '500,000 UZS',
          priceUnit: ' / mo',
          badge: 'Popular',
          description: 'Conversational practice, grammar, and IELTS preparation.',
        },
        {
          id: 'physics',
          name: 'Physics',
          grade: 'Grades 7–11',
          price: '480,000 UZS',
          priceUnit: ' / mo',
          description: 'Understanding physical concepts and solving complex problems.',
        },
      ],
    },
    results: {
      eyebrow: 'Reviews',
      title: 'Stories that inspire',
      testimonials: [
        {
          quote: 'Improved mathematics from a C to an A in one year. They focused specifically on the weak spots, so results came quickly.',
          author: 'Timur Rakhimov',
          meta: 'Grade 9 · Mathematics',
          rating: 5,
        },
        {
          quote: 'My daughter is no longer afraid to participate in class. The teacher explains things in a way that makes her want to understand.',
          author: 'Malika Saidova',
          meta: 'Parent · English',
          rating: 5,
        },
      ],
      prev: 'Previous',
      next: 'Next',
    },
    cta: {
      eyebrow: 'Free Trial Lesson',
      title: 'The first step to success starts here',
      text: 'Leave a request — we will select a program, introduce the teacher, and answer your questions.',
      name: 'Name',
      namePlaceholder: 'Your name',
      phone: 'Phone (+998)',
      phonePlaceholder: '90 123 45 67',
      course: 'Select a course',
      coursePlaceholder: 'Select a course',
      selectCourse: 'Select a course',
      button: 'Book a trial lesson',
      submitting: 'Sending…',
      success: 'Request sent',
      successText: 'We will contact you today to schedule a convenient time.',
      closeSuccess: 'Send another request',
      errName: 'Please enter your name',
      errPhone: 'Please enter a valid phone number',
      errCourse: 'Please select a course',
    },
    footer: {
      tagline: 'Helping children learn with curiosity, confidence, and visible results.',
      phone: '+998 71 200 40 40',
      email: 'hello@sfera.uz',
      address: '12 Shakhrisabz Street, Tashkent',
      hours: 'Mon–Sat, 09:00–20:00',
      nav: 'Navigation',
      rights: 'All rights reserved',
      madeWith: 'Crafted with care in Tashkent',
    },
  },
};
