export const conversationChannels = ['whatsapp', 'messenger', 'instagram'] as const;
export const conversationStatuses = ['abierta', 'cerrada', 'esperando'] as const;

export type ConversationChannel = (typeof conversationChannels)[number];
export type ConversationStatus = (typeof conversationStatuses)[number];

export type ConversationSummary = {
  id: number;
  zone: string;
  channel: ConversationChannel;
  channelId: string | null;
  phone: string | null;
  name: string | null;
  status: ConversationStatus;
  lastMessage: string | null;
  lastMessageAt: string;
};

export type ConversationCursor = {
  timestamp: string;
  id: number;
};

export type ListConversationsQuery = {
  limit: number;
  channel?: ConversationChannel;
  status?: ConversationStatus;
  cursor?: ConversationCursor;
};

export interface ConversationRepository {
  ping(): Promise<void>;
  list(query: ListConversationsQuery): Promise<ConversationSummary[]>;
}
