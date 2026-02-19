import { ContentType, PlanCode, PrismaClient } from '@prisma/client';

const prisma = new PrismaClient();

type SeedContent = {
  title: string;
  slug: string;
  type: ContentType;
  synopsis: string;
  year: number;
  duration: number;
  rating: number;
  posterUrl: string;
  bannerUrl: string;
  trailerUrl: string;
  sectionKeys: string[];
};

const contents: SeedContent[] = [
  {
    title: 'Nebula Shift',
    slug: 'nebula-shift',
    type: ContentType.MOVIE,
    synopsis:
      'Una piloto de rescate espacial descubre una señal imposible desde una colonia perdida y se enfrenta a una corporacion que controla la verdad.',
    year: 2024,
    duration: 126,
    rating: 8.2,
    posterUrl:
      'https://images.unsplash.com/photo-1536440136628-849c177e76a1?auto=format&fit=crop&w=800&q=80',
    bannerUrl:
      'https://images.unsplash.com/photo-1524985069026-dd778a71c7b4?auto=format&fit=crop&w=1600&q=80',
    trailerUrl: 'https://www.youtube.com/watch?v=5PSNL1qE6VY',
    sectionKeys: ['trending-now', 'sci-fi-night'],
  },
  {
    title: 'Codigo Aurora',
    slug: 'codigo-aurora',
    type: ContentType.SERIES,
    synopsis:
      'Un equipo de ciberforenses investiga ataques a infraestructura critica mientras una IA anonima anticipa cada movimiento.',
    year: 2025,
    duration: 48,
    rating: 8.5,
    posterUrl:
      'https://images.unsplash.com/photo-1618005198919-d3d4b5a92eee?auto=format&fit=crop&w=800&q=80',
    bannerUrl:
      'https://images.unsplash.com/photo-1440404653325-ab127d49abc1?auto=format&fit=crop&w=1600&q=80',
    trailerUrl: 'https://www.youtube.com/watch?v=zSWdZVtXT7E',
    sectionKeys: ['trending-now', 'series-estrella'],
  },
  {
    title: 'Noche en Kyoto',
    slug: 'noche-en-kyoto',
    type: ContentType.MOVIE,
    synopsis:
      'Dos extranos cruzan destinos en una ciudad luminosa mientras intentan reparar decisiones del pasado en solo una noche.',
    year: 2023,
    duration: 109,
    rating: 7.9,
    posterUrl:
      'https://images.unsplash.com/photo-1517604931442-7e0c8ed2963c?auto=format&fit=crop&w=800&q=80',
    bannerUrl:
      'https://images.unsplash.com/photo-1513106580091-1d82408b8cd6?auto=format&fit=crop&w=1600&q=80',
    trailerUrl: 'https://www.youtube.com/watch?v=6ZfuNTqbHE8',
    sectionKeys: ['estrenos', 'drama-selecto'],
  },
  {
    title: 'Valle Sombra',
    slug: 'valle-sombra',
    type: ContentType.SERIES,
    synopsis:
      'En un pueblo montanoso, cada invierno aparece un simbolo en la nieve y alguien desaparece sin dejar rastro.',
    year: 2022,
    duration: 52,
    rating: 8.1,
    posterUrl:
      'https://images.unsplash.com/photo-1489599849927-2ee91cede3ba?auto=format&fit=crop&w=800&q=80',
    bannerUrl:
      'https://images.unsplash.com/photo-1478720568477-152d9b164e26?auto=format&fit=crop&w=1600&q=80',
    trailerUrl: 'https://www.youtube.com/watch?v=b9EkMc79ZSU',
    sectionKeys: ['series-estrella', 'drama-selecto'],
  },
  {
    title: 'Radar 404',
    slug: 'radar-404',
    type: ContentType.MOVIE,
    synopsis:
      'Una periodista y un operador de radio pirata persiguen la frecuencia fantasma que predice crimenes con minutos de anticipacion.',
    year: 2025,
    duration: 118,
    rating: 8.0,
    posterUrl:
      'https://images.unsplash.com/photo-1518676590629-3dcbd9c5a5c9?auto=format&fit=crop&w=800&q=80',
    bannerUrl:
      'https://images.unsplash.com/photo-1505685296765-3a2736de412f?auto=format&fit=crop&w=1600&q=80',
    trailerUrl: 'https://www.youtube.com/watch?v=TcMBFSGVi1c',
    sectionKeys: ['estrenos', 'sci-fi-night'],
  },
  {
    title: 'Linea de Fuego',
    slug: 'linea-de-fuego',
    type: ContentType.SERIES,
    synopsis:
      'Bomberos de elite en una ciudad costera enfrentan incendios imposibles mientras una red criminal manipula emergencias.',
    year: 2024,
    duration: 45,
    rating: 7.8,
    posterUrl:
      'https://images.unsplash.com/photo-1485846234645-a62644f84728?auto=format&fit=crop&w=800&q=80',
    bannerUrl:
      'https://images.unsplash.com/photo-1542204625-de293a50dfb1?auto=format&fit=crop&w=1600&q=80',
    trailerUrl: 'https://www.youtube.com/watch?v=QdBZY2fkU-0',
    sectionKeys: ['trending-now', 'series-estrella'],
  },
  {
    title: 'Ultimo Replay',
    slug: 'ultimo-replay',
    type: ContentType.MOVIE,
    synopsis:
      'Un ex jugador profesional vuelve al circuito para salvar su equipo y descubre una conspiracion de apuestas digitales.',
    year: 2021,
    duration: 102,
    rating: 7.5,
    posterUrl:
      'https://images.unsplash.com/photo-1478720568477-152d9b164e26?auto=format&fit=crop&w=800&q=80',
    bannerUrl:
      'https://images.unsplash.com/photo-1487180144351-b8472da7d491?auto=format&fit=crop&w=1600&q=80',
    trailerUrl: 'https://www.youtube.com/watch?v=5MgBikgcWnY',
    sectionKeys: ['drama-selecto'],
  },
  {
    title: 'Archivo Violeta',
    slug: 'archivo-violeta',
    type: ContentType.SERIES,
    synopsis:
      'Una archivista judicial encuentra expedientes ocultos que conectan casos cerrados durante tres decadas.',
    year: 2026,
    duration: 50,
    rating: 8.4,
    posterUrl:
      'https://images.unsplash.com/photo-1574375927938-d5a98e8ffe85?auto=format&fit=crop&w=800&q=80',
    bannerUrl:
      'https://images.unsplash.com/photo-1509347528160-9a9e33742cdb?auto=format&fit=crop&w=1600&q=80',
    trailerUrl: 'https://www.youtube.com/watch?v=YR5x2Z6I1X0',
    sectionKeys: ['estrenos', 'series-estrella', 'drama-selecto'],
  },
];

async function main() {
  const plans = [
    { code: PlanCode.BASIC, name: 'Basic', priceMonthly: '119.00', quality: 'HD', screens: 1 },
    {
      code: PlanCode.STANDARD,
      name: 'Standard',
      priceMonthly: '189.00',
      quality: 'Full HD',
      screens: 2,
    },
    {
      code: PlanCode.PREMIUM,
      name: 'Premium',
      priceMonthly: '259.00',
      quality: '4K + HDR',
      screens: 4,
    },
  ];

  for (const plan of plans) {
    await prisma.subscriptionPlan.upsert({
      where: { code: plan.code },
      update: {
        name: plan.name,
        priceMonthly: plan.priceMonthly,
        quality: plan.quality,
        screens: plan.screens,
      },
      create: plan,
    });
  }

  const sections = [
    {
      key: 'trending-now',
      name: 'Tendencias ahora',
      description: 'Lo mas visto esta semana por los usuarios.',
      isHomeVisible: true,
      sortOrder: 1,
    },
    {
      key: 'estrenos',
      name: 'Estrenos recientes',
      description: 'Nuevos lanzamientos para maratonear.',
      isHomeVisible: true,
      sortOrder: 2,
    },
    {
      key: 'series-estrella',
      name: 'Series estrella',
      description: 'Series para engancharte episodio tras episodio.',
      isHomeVisible: true,
      sortOrder: 3,
    },
    {
      key: 'sci-fi-night',
      name: 'Sci-fi night',
      description: 'Ciencia ficcion con suspenso y tecnologia.',
      isHomeVisible: true,
      sortOrder: 4,
    },
    {
      key: 'drama-selecto',
      name: 'Drama selecto',
      description: 'Historias intensas con personajes inolvidables.',
      isHomeVisible: true,
      sortOrder: 5,
    },
  ];

  const sectionByKey: Record<string, { id: string }> = {};

  for (const section of sections) {
    const saved = await prisma.section.upsert({
      where: { key: section.key },
      update: {
        name: section.name,
        description: section.description,
        isHomeVisible: section.isHomeVisible,
        sortOrder: section.sortOrder,
      },
      create: section,
      select: { id: true },
    });
    sectionByKey[section.key] = saved;
  }

  for (const item of contents) {
    const content = await prisma.content.upsert({
      where: { slug: item.slug },
      update: {
        title: item.title,
        type: item.type,
        synopsis: item.synopsis,
        year: item.year,
        duration: item.duration,
        rating: item.rating,
        posterUrl: item.posterUrl,
        bannerUrl: item.bannerUrl,
        trailerUrl: item.trailerUrl,
        isActive: true,
      },
      create: {
        title: item.title,
        slug: item.slug,
        type: item.type,
        synopsis: item.synopsis,
        year: item.year,
        duration: item.duration,
        rating: item.rating,
        posterUrl: item.posterUrl,
        bannerUrl: item.bannerUrl,
        trailerUrl: item.trailerUrl,
        isActive: true,
      },
      select: { id: true },
    });

    await prisma.contentSection.deleteMany({ where: { contentId: content.id } });

    for (let index = 0; index < item.sectionKeys.length; index += 1) {
      const key = item.sectionKeys[index];
      const section = sectionByKey[key];
      if (!section) {
        continue;
      }

      await prisma.contentSection.create({
        data: {
          contentId: content.id,
          sectionId: section.id,
          sortOrder: index,
        },
      });
    }
  }

  // eslint-disable-next-line no-console
  console.log('Seed completado: planes, secciones y contenido inicial listos.');
}

main()
  .catch((error) => {
    // eslint-disable-next-line no-console
    console.error('Error al ejecutar seed:', error);
    process.exit(1);
  })
  .finally(async () => {
    await prisma.$disconnect();
  });
