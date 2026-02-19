import { ContentType } from '@prisma/client';
import {
  IsArray,
  IsBoolean,
  IsEnum,
  IsInt,
  IsNumber,
  IsOptional,
  IsString,
  Max,
  Min,
  MinLength,
} from 'class-validator';

export class CreateContentDto {
  @IsString()
  @MinLength(2)
  title!: string;

  @IsEnum(ContentType)
  type!: ContentType;

  @IsString()
  @MinLength(20)
  synopsis!: string;

  @IsInt()
  @Min(1900)
  @Max(2100)
  year!: number;

  @IsInt()
  @Min(1)
  duration!: number;

  @IsNumber({ maxDecimalPlaces: 1 })
  @Min(0)
  @Max(10)
  rating!: number;

  @IsString()
  posterUrl!: string;

  @IsString()
  bannerUrl!: string;

  @IsString()
  trailerUrl!: string;

  @IsOptional()
  @IsBoolean()
  isActive?: boolean;

  @IsOptional()
  @IsArray()
  @IsString({ each: true })
  sectionIds?: string[];
}
