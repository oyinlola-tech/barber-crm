export type WhatsAppConnection = {
    id: number;
    phone_number_id: string;
    business_account_id: string;
    status: string;
    connected_at: string | null;
};

export type WhatsAppCredentials = {
    phone_number_id: string;
    business_account_id: string;
    access_token: string;
};
