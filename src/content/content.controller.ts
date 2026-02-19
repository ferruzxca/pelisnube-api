import { Controller, Get, Param, Query } from '@nestjs/common';
import { ContentService } from './content.service';
import { QueryContentDto } from './dto/query-content.dto';

@Controller('content')
export class ContentController {
  constructor(private readonly contentService: ContentService) {}

  @Get()
  list(@Query() query: QueryContentDto) {
    return this.contentService.list(query);
  }

  @Get(':id')
  findOne(@Param('id') id: string) {
    return this.contentService.findOne(id);
  }
}
