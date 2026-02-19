import { Injectable, NotFoundException } from '@nestjs/common';
import { Content, Prisma } from '@prisma/client';
import { PrismaService } from '../prisma/prisma.service';
import { QueryContentDto } from './dto/query-content.dto';

const contentSelect = {
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
  sections: {
    select: {
      sortOrder: true,
      section: {
        select: {
          id: true,
          key: true,
          name: true,
        },
      },
    },
    orderBy: {
      sortOrder: 'asc' as const,
    },
  },
} satisfies Prisma.ContentSelect;

@Injectable()
export class ContentService {
  constructor(private readonly prisma: PrismaService) {}

  async list(query: QueryContentDto) {
    const where: Prisma.ContentWhereInput = {
      isActive: true,
    };

    if (query.type) {
      where.type = query.type;
    }

    if (query.search) {
      where.OR = [
        {
          title: {
            contains: query.search,
          },
        },
        {
          synopsis: {
            contains: query.search,
          },
        },
      ];
    }

    if (query.section) {
      where.sections = {
        some: {
          section: {
            key: query.section,
          },
        },
      };
    }

    const contents = await this.prisma.content.findMany({
      where,
      select: contentSelect,
      orderBy: [{ year: 'desc' }, { title: 'asc' }],
    });

    return contents.map((item) => this.serializeContent(item));
  }

  async findOne(contentIdOrSlug: string) {
    const content = await this.prisma.content.findFirst({
      where: {
        isActive: true,
        OR: [{ id: contentIdOrSlug }, { slug: contentIdOrSlug }],
      },
      select: contentSelect,
    });

    if (!content) {
      throw new NotFoundException('Contenido no encontrado.');
    }

    return this.serializeContent(content);
  }

  private serializeContent(content: ContentWithSection) {
    return {
      id: content.id,
      title: content.title,
      slug: content.slug,
      type: content.type,
      synopsis: content.synopsis,
      year: content.year,
      duration: content.duration,
      rating: content.rating,
      posterUrl: content.posterUrl,
      bannerUrl: content.bannerUrl,
      trailerUrl: content.trailerUrl,
      sections: content.sections.map((record) => ({
        id: record.section.id,
        key: record.section.key,
        name: record.section.name,
        sortOrder: record.sortOrder,
      })),
    };
  }
}

type ContentWithSection = Prisma.ContentGetPayload<{
  select: typeof contentSelect;
}>;
