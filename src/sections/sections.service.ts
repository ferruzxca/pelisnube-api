import { Injectable } from '@nestjs/common';
import { PrismaService } from '../prisma/prisma.service';

@Injectable()
export class SectionsService {
  constructor(private readonly prisma: PrismaService) {}

  async getHomeSections() {
    const sections = await this.prisma.section.findMany({
      where: { isHomeVisible: true },
      orderBy: [{ sortOrder: 'asc' }, { name: 'asc' }],
      select: {
        id: true,
        key: true,
        name: true,
        description: true,
        sortOrder: true,
        contents: {
          orderBy: {
            sortOrder: 'asc',
          },
          select: {
            sortOrder: true,
            content: {
              select: {
                id: true,
                title: true,
                slug: true,
                type: true,
                synopsis: true,
                year: true,
                duration: true,
                rating: true,
                posterUrl: true,
                bannerUrl: true,
                trailerUrl: true,
                isActive: true,
              },
            },
          },
        },
      },
    });

    return sections.map((section) => ({
      id: section.id,
      key: section.key,
      name: section.name,
      description: section.description,
      sortOrder: section.sortOrder,
      items: section.contents
        .filter((entry) => entry.content.isActive)
        .map((entry) => ({
          id: entry.content.id,
          title: entry.content.title,
          slug: entry.content.slug,
          type: entry.content.type,
          synopsis: entry.content.synopsis,
          year: entry.content.year,
          duration: entry.content.duration,
          rating: entry.content.rating,
          posterUrl: entry.content.posterUrl,
          bannerUrl: entry.content.bannerUrl,
          trailerUrl: entry.content.trailerUrl,
          sortOrder: entry.sortOrder,
        })),
    }));
  }
}
