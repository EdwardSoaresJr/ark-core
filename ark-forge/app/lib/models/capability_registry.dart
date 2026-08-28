class CapabilityRegistry {
  CapabilityRegistry({
    required this.registryCapabilityId,
    required this.capabilities,
  });

  factory CapabilityRegistry.fromJson(Map<String, dynamic> json) {
    return CapabilityRegistry(
      registryCapabilityId: json['registry_capability_id'] as String,
      capabilities: (json['capabilities'] as List<dynamic>)
          .map((e) => CapabilityIdentity.fromJson(e as Map<String, dynamic>))
          .toList(),
    );
  }

  final String registryCapabilityId;
  final List<CapabilityIdentity> capabilities;

  int get invokeCount =>
      capabilities.where((c) => c.mode == 'invoke').length;

  int get observeCount =>
      capabilities.where((c) => c.mode == 'observe').length;
}

class CapabilityIdentity {
  CapabilityIdentity({
    required this.id,
    required this.displayName,
    required this.domain,
    required this.resource,
    required this.operation,
    required this.mode,
    required this.version,
    required this.stability,
    required this.available,
    required this.arguments,
  });

  factory CapabilityIdentity.fromJson(Map<String, dynamic> json) {
    return CapabilityIdentity(
      id: json['id'] as String,
      displayName: json['display_name'] as String,
      domain: json['domain'] as String,
      resource: json['resource'] as String,
      operation: json['operation'] as String,
      mode: json['mode'] as String,
      version: json['version'] as int,
      stability: json['stability'] as String,
      available: json['available'] as bool,
      arguments: (json['arguments'] as List<dynamic>)
          .map((e) => CapabilityArgument.fromJson(e as Map<String, dynamic>))
          .toList(),
    );
  }

  final String id;
  final String displayName;
  final String domain;
  final String resource;
  final String operation;
  final String mode;
  final int version;
  final String stability;
  final bool available;
  final List<CapabilityArgument> arguments;
}

class CapabilityArgument {
  CapabilityArgument({
    required this.name,
    required this.type,
    required this.isRequired,
  });

  factory CapabilityArgument.fromJson(Map<String, dynamic> json) {
    return CapabilityArgument(
      name: json['name'] as String,
      type: json['type'] as String,
      isRequired: json['required'] as bool,
    );
  }

  final String name;
  final String type;
  final bool isRequired;
}
